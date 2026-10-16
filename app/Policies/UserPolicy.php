<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageUsers();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageUsers();
    }
}
