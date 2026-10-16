<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Order state machine:
 *
 *   pending --confirm--> confirmed
 *   pending --cancel---> cancelled
 *
 * confirmed and cancelled are final.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => $next === self::Confirmed || $next === self::Cancelled,
            self::Confirmed, self::Cancelled => false,
        };
    }
}
