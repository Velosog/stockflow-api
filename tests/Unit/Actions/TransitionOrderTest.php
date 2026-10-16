<?php

declare(strict_types=1);

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\CreateOrder;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->manager = User::factory()->manager()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create();
    receiveStock($this->product, $this->warehouse, 10);

    $this->order = app(CreateOrder::class)->handle($this->manager, $this->warehouse->id, [
        ['product_id' => $this->product->id, 'quantity' => 4],
    ]);
});

it('confirms an order by turning its reservation into an outbound movement', function (): void {
    $order = app(ConfirmOrder::class)->handle($this->order, $this->manager);

    expect($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->confirmed_at)->not->toBeNull();

    $level = stockLevel($this->product, $this->warehouse);
    expect([$level->on_hand, $level->reserved, $level->available])->toBe([6, 0, 6]);

    expect(StockMovement::query()->where('order_id', $order->id)->pluck('type')->all())
        ->toBe([StockMovementType::Reservation, StockMovementType::Outbound]);
});

it('cancels an order by releasing its reservation', function (): void {
    $order = app(CancelOrder::class)->handle($this->order, $this->manager, 'Customer request');

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancellation_reason)->toBe('Customer request');

    $level = stockLevel($this->product, $this->warehouse);
    expect([$level->on_hand, $level->reserved, $level->available])->toBe([10, 0, 10]);
});

it('rejects transitions out of a final state without touching stock', function (string $first, string $second): void {
    $actions = ['confirm' => ConfirmOrder::class, 'cancel' => CancelOrder::class];
    app($actions[$first])->handle($this->order, $this->manager);
    $movementsBefore = StockMovement::query()->count();

    expect(fn () => app($actions[$second])->handle($this->order, $this->manager))
        ->toThrow(InvalidOrderTransitionException::class);

    expect(StockMovement::query()->count())->toBe($movementsBefore);
})->with([
    'confirm twice' => ['confirm', 'confirm'],
    'cancel after confirm' => ['confirm', 'cancel'],
    'confirm after cancel' => ['cancel', 'confirm'],
    'cancel twice' => ['cancel', 'cancel'],
]);

it('uses the current database state, not a stale model', function (): void {
    $stale = Order::query()->findOrFail($this->order->id);
    app(CancelOrder::class)->handle($this->order, $this->manager);

    // $stale still says "pending", but the row is locked and re-read first.
    expect(fn () => app(ConfirmOrder::class)->handle($stale, $this->manager))
        ->toThrow(InvalidOrderTransitionException::class);
});
