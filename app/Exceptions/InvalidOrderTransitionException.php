<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;

final class InvalidOrderTransitionException extends DomainRuleException
{
    public function __construct(private readonly OrderStatus $from, private readonly OrderStatus $to)
    {
        parent::__construct("An order cannot go from {$from->value} to {$to->value}.");
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'invalid_status_transition';
    }

    /**
     * @return array{current_status: string, requested_status: string}
     */
    public function context(): array
    {
        return ['current_status' => $this->from->value, 'requested_status' => $this->to->value];
    }
}
