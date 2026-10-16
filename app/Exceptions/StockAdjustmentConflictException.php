<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A negative adjustment would leave fewer units on hand than are reserved
 * for open orders.
 */
final class StockAdjustmentConflictException extends DomainRuleException
{
    public function __construct(private readonly int $onHand, private readonly int $reserved, private readonly int $quantity)
    {
        parent::__construct('The adjustment would leave less stock on hand than is reserved for open orders.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'stock_adjustment_conflict';
    }

    /**
     * @return array{on_hand: int, reserved: int, quantity: int}
     */
    public function context(): array
    {
        return ['on_hand' => $this->onHand, 'reserved' => $this->reserved, 'quantity' => $this->quantity];
    }
}
