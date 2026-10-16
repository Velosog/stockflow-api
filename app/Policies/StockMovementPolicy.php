<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StockMovementType;
use App\Models\User;

final class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Anyone may record goods received; only managers and admins may correct
     * on-hand quantities with an adjustment.
     */
    public function record(User $user, StockMovementType $type): bool
    {
        return match ($type) {
            StockMovementType::Inbound => true,
            StockMovementType::Adjustment => $user->role->canAdjustStock(),
            default => false,
        };
    }
}
