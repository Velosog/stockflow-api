<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Shared mechanics of confirming and cancelling: lock the order row so two
 * concurrent requests cannot both act on a pending order, check the state
 * machine, then move each line's reserved stock.
 */
final class TransitionOrder
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  array<string, mixed>  $attributes  extra order columns to set
     */
    public function handle(Order $order, OrderStatus $to, StockMovementType $movement, User $actor, array $attributes = []): Order
    {
        return DB::transaction(function () use ($order, $to, $movement, $actor, $attributes): Order {
            // Lock the order first and stock levels second: every code path
            // takes locks in this order, so they cannot deadlock.
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->sole();

            if (! $locked->status->canTransitionTo($to)) {
                throw new InvalidOrderTransitionException($locked->status, $to);
            }

            $items = $locked->items()->orderBy('product_id')->get();
            $levels = $this->ledger->lockLevels(
                $locked->warehouse_id,
                $items->map(fn (OrderItem $item): int => $item->product_id)->values()->all(),
            );

            foreach ($items as $item) {
                $level = $levels->get($item->product_id)
                    ?? throw new LogicException("Order {$locked->id} has a reservation without a stock level.");

                $this->ledger->record($level, $movement, $item->quantity, order: $locked, performedBy: $actor);
            }

            $locked->update(['status' => $to, ...$attributes]);

            return $locked;
        });
    }
}
