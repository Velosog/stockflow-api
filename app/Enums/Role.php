<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * User roles. The permission matrix lives here so policies stay one-liners
 * and the whole matrix can be read (and tested) in a single place.
 */
enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Operator = 'operator';

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canManageWarehouses(): bool
    {
        return $this === self::Admin;
    }

    public function canManageCatalog(): bool
    {
        return $this === self::Admin || $this === self::Manager;
    }

    /** Adjustments correct on-hand quantities (counts, damage, losses). */
    public function canAdjustStock(): bool
    {
        return $this === self::Admin || $this === self::Manager;
    }

    /** Confirming or cancelling any order, not only one's own. */
    public function canApproveOrders(): bool
    {
        return $this === self::Admin || $this === self::Manager;
    }

    public function receivesLowStockAlerts(): bool
    {
        return $this === self::Manager;
    }
}
