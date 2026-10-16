<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function confirm(User $user, Order $order): bool
    {
        return $user->role->canApproveOrders();
    }

    /**
     * Managers and admins may cancel any order; operators only their own.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->role->canApproveOrders() || $order->created_by === $user->id;
    }
}
