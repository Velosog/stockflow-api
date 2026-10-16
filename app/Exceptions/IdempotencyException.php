<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Problems with an Idempotency-Key header (format, reuse, concurrent use).
 */
final class IdempotencyException extends DomainRuleException
{
    private function __construct(string $message, private readonly int $httpStatus, private readonly string $problemCode)
    {
        parent::__construct($message);
    }

    public static function invalidKey(): self
    {
        return new self('The Idempotency-Key header must be 1 to 255 visible ASCII characters.', 400, 'invalid_idempotency_key');
    }

    public static function reusedWithDifferentPayload(): self
    {
        return new self('This Idempotency-Key was already used with a different request body.', 422, 'idempotency_key_reused');
    }

    public static function stillProcessing(): self
    {
        return new self('A request with this Idempotency-Key is still being processed. Retry shortly.', 409, 'idempotency_request_in_progress');
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->problemCode;
    }
}
