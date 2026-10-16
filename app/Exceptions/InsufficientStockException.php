<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Not enough available stock to reserve every requested line.
 */
final class InsufficientStockException extends DomainRuleException
{
    /**
     * @param  list<array{product_id: int, sku: string, requested: int, available: int}>  $shortages
     */
    public function __construct(private readonly array $shortages)
    {
        parent::__construct('Not enough available stock for one or more items.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'insufficient_stock';
    }

    /**
     * @return array{shortages: list<array{product_id: int, sku: string, requested: int, available: int}>}
     */
    public function context(): array
    {
        return ['shortages' => $this->shortages];
    }
}
