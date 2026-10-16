<?php

declare(strict_types=1);

namespace App\Exceptions;

final class WarehouseInUseException extends DomainRuleException
{
    public function __construct()
    {
        parent::__construct('This warehouse has stock history or orders and cannot be deleted.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'warehouse_in_use';
    }
}
