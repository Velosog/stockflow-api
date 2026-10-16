<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockAdjustmentConflictException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->ledger = app(StockLedger::class);
    $this->product = Product::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
});

function apply(StockMovementType $type, int $quantity): StockMovement
{
    return DB::transaction(function () use ($type, $quantity): StockMovement {
        $ledger = test()->ledger;
        $level = $ledger->lockLevel(test()->product->id, test()->warehouse->id);

        return $ledger->record($level, $type, $quantity);
    });
}

it('creates the stock level on the first movement and records running balances', function (): void {
    $movement = apply(StockMovementType::Inbound, 10);

    expect($movement->on_hand_after)->toBe(10)
        ->and($movement->reserved_after)->toBe(0);

    $level = stockLevel($this->product, $this->warehouse);
    expect([$level->on_hand, $level->reserved, $level->available])->toBe([10, 0, 10]);
});

it('keeps levels equal to the sum of the ledger', function (): void {
    apply(StockMovementType::Inbound, 10);
    apply(StockMovementType::Reservation, 4);
    apply(StockMovementType::Outbound, 3);
    apply(StockMovementType::Release, 1);
    apply(StockMovementType::Adjustment, -2);

    $level = stockLevel($this->product, $this->warehouse);
    $ledger = StockMovement::query()->get();

    expect($level->on_hand)->toBe($ledger->sum(fn (StockMovement $m) => $m->type->onHandDelta($m->quantity)))
        ->and($level->reserved)->toBe($ledger->sum(fn (StockMovement $m) => $m->type->reservedDelta($m->quantity)))
        ->and([$level->on_hand, $level->reserved, $level->available])->toBe([5, 0, 5]);
});

it('refuses to reserve more than is available', function (): void {
    apply(StockMovementType::Inbound, 3);

    expect(fn () => apply(StockMovementType::Reservation, 4))->toThrow(InsufficientStockException::class);
    expect(stockLevel($this->product, $this->warehouse)->reserved)->toBe(0)
        ->and(StockMovement::query()->count())->toBe(1);
});

it('refuses an adjustment that would leave less on hand than reserved', function (): void {
    apply(StockMovementType::Inbound, 5);
    apply(StockMovementType::Reservation, 4);

    expect(fn () => apply(StockMovementType::Adjustment, -2))->toThrow(StockAdjustmentConflictException::class);
});

it('treats releasing more than was reserved as a bug', function (): void {
    apply(StockMovementType::Inbound, 5);

    expect(fn () => apply(StockMovementType::Release, 1))->toThrow(LogicException::class);
});

it('rejects quantities that do not match the movement type', function (StockMovementType $type, int $quantity): void {
    expect(fn () => apply($type, $quantity))->toThrow(LogicException::class);
})->with([
    'zero inbound' => [StockMovementType::Inbound, 0],
    'negative reservation' => [StockMovementType::Reservation, -1],
    'zero adjustment' => [StockMovementType::Adjustment, 0],
]);

it('is enforced as append-only by the database', function (string $operation): void {
    $movement = apply(StockMovementType::Inbound, 10);

    // A savepoint keeps the test transaction usable after the failed statement.
    expect(fn () => DB::transaction(fn () => $operation === 'update'
        ? StockMovement::query()->whereKey($movement->id)->update(['quantity' => 999])
        : StockMovement::query()->whereKey($movement->id)->delete()))
        ->toThrow(QueryException::class, 'append-only');
})->with(['update', 'delete']);

it('enforces stock invariants in the database even if code is bypassed', function (): void {
    apply(StockMovementType::Inbound, 2);

    expect(fn () => DB::transaction(fn () => DB::table('stock_levels')->update(['reserved' => 3])))
        ->toThrow(QueryException::class, 'stock_levels_reserved_check');
});
