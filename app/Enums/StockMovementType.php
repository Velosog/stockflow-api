<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every change to stock is one of these movements. Each type has a fixed
 * effect on the two stored quantities (available = on_hand - reserved):
 *
 *   type         on_hand    reserved   origin
 *   inbound      +q         0          goods received (API)
 *   adjustment   +/-q       0          stock count correction (API)
 *   reservation  0          +q         order created
 *   release      0          -q         order cancelled
 *   outbound     -q         -q         order confirmed (ships the reservation)
 */
enum StockMovementType: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Adjustment = 'adjustment';
    case Reservation = 'reservation';
    case Release = 'release';

    public function onHandDelta(int $quantity): int
    {
        return match ($this) {
            self::Inbound, self::Adjustment => $quantity,
            self::Outbound => -$quantity,
            self::Reservation, self::Release => 0,
        };
    }

    public function reservedDelta(int $quantity): int
    {
        return match ($this) {
            self::Reservation => $quantity,
            self::Release, self::Outbound => -$quantity,
            self::Inbound, self::Adjustment => 0,
        };
    }

    /** Types a user may record directly; the others only come from orders. */
    public function isManual(): bool
    {
        return $this === self::Inbound || $this === self::Adjustment;
    }

    /**
     * @return list<string>
     */
    public static function manualValues(): array
    {
        return [self::Inbound->value, self::Adjustment->value];
    }
}
