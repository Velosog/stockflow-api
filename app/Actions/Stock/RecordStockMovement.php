<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a manual movement: goods received (inbound) or a stock count
 * correction (adjustment). Order-driven movements go through the order actions.
 */
final class RecordStockMovement
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(
        User $performedBy,
        int $productId,
        int $warehouseId,
        StockMovementType $type,
        int $quantity,
        ?string $note = null,
    ): StockMovement {
        if (! $type->isManual()) {
            throw new InvalidArgumentException("{$type->value} movements are created by orders only.");
        }

        return DB::transaction(function () use ($performedBy, $productId, $warehouseId, $type, $quantity, $note): StockMovement {
            $level = $this->ledger->lockLevel($productId, $warehouseId);

            return $this->ledger->record($level, $type, $quantity, performedBy: $performedBy, note: $note);
        });
    }
}
