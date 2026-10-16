<?php

declare(strict_types=1);

use App\Enums\StockMovementType;

it('applies the documented effect of each movement type', function (StockMovementType $type, int $onHand, int $reserved): void {
    expect($type->onHandDelta(5))->toBe($onHand)
        ->and($type->reservedDelta(5))->toBe($reserved);
})->with([
    'inbound' => [StockMovementType::Inbound, 5, 0],
    'adjustment' => [StockMovementType::Adjustment, 5, 0],
    'reservation' => [StockMovementType::Reservation, 0, 5],
    'release' => [StockMovementType::Release, 0, -5],
    'outbound' => [StockMovementType::Outbound, -5, -5],
]);

it('keeps available stock unchanged when a reservation ships', function (): void {
    $type = StockMovementType::Outbound;

    // available = on_hand - reserved, both drop by the same amount.
    expect($type->onHandDelta(3) - $type->reservedDelta(3))->toBe(0);
});

it('only allows inbound and adjustment to be recorded manually', function (): void {
    expect(StockMovementType::manualValues())->toBe(['inbound', 'adjustment'])
        ->and(StockMovementType::Reservation->isManual())->toBeFalse();
});
