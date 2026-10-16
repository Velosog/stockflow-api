<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\User;

/**
 * pending -> confirmed: every reservation becomes an outbound movement
 * (stock leaves the warehouse).
 */
final class ConfirmOrder
{
    public function __construct(private readonly TransitionOrder $transition) {}

    public function handle(Order $order, User $actor): Order
    {
        return $this->transition->handle($order, OrderStatus::Confirmed, StockMovementType::Outbound, $actor, [
            'confirmed_at' => now(),
        ]);
    }
}
