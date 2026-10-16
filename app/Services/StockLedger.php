<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockAdjustmentConflictException;
use App\Jobs\SendLowStockAlert;
use App\Models\Order;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only code that changes stock. Every change:
 *
 *   1. runs inside a database transaction,
 *   2. holds a row lock (SELECT ... FOR UPDATE) on the affected stock_levels row,
 *   3. updates on_hand / reserved and appends one stock_movements row.
 *
 * Because the level row is locked until commit, concurrent requests for the
 * same product and warehouse are serialised and cannot oversell.
 */
final class StockLedger
{
    /**
     * Lock the stock level of one product in one warehouse, creating an empty
     * level first if this is the first movement for that pair.
     */
    public function lockLevel(int $productId, int $warehouseId): StockLevel
    {
        $this->ensureInTransaction();

        // INSERT ... ON CONFLICT DO NOTHING: safe when two requests create the
        // same level at the same time.
        StockLevel::query()->insertOrIgnore([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'on_hand' => 0,
            'reserved' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->sole();
    }

    /**
     * Lock the existing stock levels of several products in one warehouse.
     *
     * Rows are always locked in ascending product_id order. Two transactions
     * that lock overlapping sets therefore acquire locks in the same order and
     * cannot deadlock each other. Products without a level row have no stock.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, StockLevel> keyed by product_id
     */
    public function lockLevels(int $warehouseId, array $productIds): Collection
    {
        $this->ensureInTransaction();

        return StockLevel::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Apply a movement to a level locked by this transaction and append it to
     * the ledger.
     */
    public function record(
        StockLevel $level,
        StockMovementType $type,
        int $quantity,
        ?Order $order = null,
        ?User $performedBy = null,
        ?string $note = null,
    ): StockMovement {
        $this->ensureInTransaction();
        $this->ensureValidQuantity($type, $quantity);

        $onHand = $level->on_hand + $type->onHandDelta($quantity);
        $reserved = $level->reserved + $type->reservedDelta($quantity);

        $this->ensureInvariants($level, $type, $quantity, $onHand, $reserved);

        $availableBefore = $level->available;

        $level->on_hand = $onHand;
        $level->reserved = $reserved;
        $level->save();
        // Re-read the generated "available" column computed by PostgreSQL.
        $level->refresh();

        $movement = StockMovement::query()->create([
            'product_id' => $level->product_id,
            'warehouse_id' => $level->warehouse_id,
            'type' => $type,
            'quantity' => $quantity,
            'on_hand_after' => $onHand,
            'reserved_after' => $reserved,
            'order_id' => $order?->id,
            'performed_by' => $performedBy?->id,
            'note' => $note,
        ]);

        $this->alertIfCrossedLowStockThreshold($level, $availableBefore);

        return $movement;
    }

    /**
     * Alert only when available stock crosses the threshold downwards, not on
     * every movement while it stays low. afterCommit(): the job is queued only
     * once this transaction commits, so a rolled-back reservation never
     * alerts and the worker never reads uncommitted data.
     */
    private function alertIfCrossedLowStockThreshold(StockLevel $level, int $availableBefore): void
    {
        if ($level->available >= $availableBefore) {
            return;
        }

        $threshold = (int) $level->product()->value('low_stock_threshold');

        if ($availableBefore >= $threshold && $level->available < $threshold) {
            SendLowStockAlert::dispatch($level->id)->afterCommit();
        }
    }

    private function ensureInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Stock changes must run inside a database transaction.');
        }
    }

    private function ensureValidQuantity(StockMovementType $type, int $quantity): void
    {
        $valid = $type === StockMovementType::Adjustment ? $quantity !== 0 : $quantity > 0;

        if (! $valid) {
            throw new LogicException("Invalid quantity {$quantity} for a {$type->value} movement.");
        }
    }

    private function ensureInvariants(StockLevel $level, StockMovementType $type, int $quantity, int $onHand, int $reserved): void
    {
        if ($onHand >= 0 && $reserved >= 0 && $reserved <= $onHand) {
            return;
        }

        throw match ($type) {
            // Expected business outcomes, reported to the client.
            StockMovementType::Reservation => new InsufficientStockException([[
                'product_id' => $level->product_id,
                'sku' => (string) $level->product()->value('sku'),
                'requested' => $quantity,
                'available' => $level->available,
            ]]),
            StockMovementType::Adjustment => new StockAdjustmentConflictException($level->on_hand, $level->reserved, $quantity),
            // Releasing or shipping more than was reserved means a bug upstream.
            default => new LogicException("A {$type->value} of {$quantity} would break stock invariants for level {$level->id}."),
        };
    }
}
