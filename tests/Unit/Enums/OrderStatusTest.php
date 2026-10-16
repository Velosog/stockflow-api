<?php

declare(strict_types=1);

use App\Enums\OrderStatus;

it('implements the order state machine', function (OrderStatus $from, OrderStatus $to, bool $allowed): void {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [OrderStatus::Pending, OrderStatus::Confirmed, true],
    [OrderStatus::Pending, OrderStatus::Cancelled, true],
    [OrderStatus::Pending, OrderStatus::Pending, false],
    [OrderStatus::Confirmed, OrderStatus::Cancelled, false],
    [OrderStatus::Confirmed, OrderStatus::Pending, false],
    [OrderStatus::Cancelled, OrderStatus::Confirmed, false],
    [OrderStatus::Cancelled, OrderStatus::Pending, false],
]);
