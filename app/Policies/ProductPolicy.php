<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canManageCatalog();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->role->canManageCatalog();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->role->canManageCatalog();
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->role->canManageCatalog();
    }
}
