<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule was violated (e.g. not enough stock, invalid status change).
 * These are expected outcomes, rendered as client errors and never reported
 * to the error log as bugs.
 */
abstract class DomainRuleException extends RuntimeException
{
    abstract public function status(): int;

    /** Stable, machine-readable identifier clients can branch on. */
    abstract public function errorCode(): string;

    /**
     * Extra members added to the problem details body.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }
}
