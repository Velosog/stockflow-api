<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

final class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canManageWarehouses();
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->role->canManageWarehouses();
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->role->canManageWarehouses();
    }
}
