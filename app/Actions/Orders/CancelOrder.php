<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\User;

/**
 * pending -> cancelled: every reservation is released back to available stock.
 */
final class CancelOrder
{
    public function __construct(private readonly TransitionOrder $transition) {}

    public function handle(Order $order, User $actor, ?string $reason = null): Order
    {
        return $this->transition->handle($order, OrderStatus::Cancelled, StockMovementType::Release, $actor, [
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
