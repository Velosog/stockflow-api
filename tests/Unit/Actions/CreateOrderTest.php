<?php

declare(strict_types=1);

use App\Actions\Orders\CreateOrder;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->bolts = Product::factory()->create(['sku' => 'HB-M8-100', 'price_cents' => 1890]);
    $this->cable = Product::factory()->create(['sku' => 'CB-CAT6-30', 'price_cents' => 2450]);
    receiveStock($this->bolts, $this->warehouse, 10);
    receiveStock($this->cable, $this->warehouse, 5);
});

it('creates a pending order, snapshots prices and reserves every line', function (): void {
    $order = app(CreateOrder::class)->handle($this->user, $this->warehouse->id, [
        ['product_id' => $this->bolts->id, 'quantity' => 4],
        ['product_id' => $this->cable->id, 'quantity' => 2],
    ], 'CUST-0042');

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_cents)->toBe(4 * 1890 + 2 * 2450)
        ->and($order->customer_reference)->toBe('CUST-0042')
        ->and($order->items()->count())->toBe(2);

    $bolts = stockLevel($this->bolts, $this->warehouse);
    expect([$bolts->on_hand, $bolts->reserved, $bolts->available])->toBe([10, 4, 6]);

    expect(StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::Reservation)->count())->toBe(2);

    // A later price change does not alter the order.
    $this->bolts->update(['price_cents' => 9999]);
    expect($order->items()->where('product_id', $this->bolts->id)->value('unit_price_cents'))->toBe(1890);
});

it('reserves nothing when any line is short, and reports every shortage', function (): void {
    $unstocked = Product::factory()->create(['sku' => 'NO-STOCK-1']);

    try {
        app(CreateOrder::class)->handle($this->user, $this->warehouse->id, [
            ['product_id' => $this->bolts->id, 'quantity' => 4],
            ['product_id' => $this->cable->id, 'quantity' => 6],
            ['product_id' => $unstocked->id, 'quantity' => 1],
        ]);
        $this->fail('Expected InsufficientStockException.');
    } catch (InsufficientStockException $e) {
        expect($e->context()['shortages'])->toBe([
            ['product_id' => $this->cable->id, 'sku' => 'CB-CAT6-30', 'requested' => 6, 'available' => 5],
            ['product_id' => $unstocked->id, 'sku' => 'NO-STOCK-1', 'requested' => 1, 'available' => 0],
        ]);
    }

    expect(Order::query()->count())->toBe(0)
        ->and(stockLevel($this->bolts, $this->warehouse)->reserved)->toBe(0)
        ->and(StockMovement::query()->where('type', StockMovementType::Reservation)->count())->toBe(0);
});

it('only uses stock from the order warehouse', function (): void {
    $otherWarehouse = Warehouse::factory()->create();

    expect(fn () => app(CreateOrder::class)->handle($this->user, $otherWarehouse->id, [
        ['product_id' => $this->bolts->id, 'quantity' => 1],
    ]))->toThrow(InsufficientStockException::class);
});

it('re-checks that products are still active inside the transaction', function (): void {
    $this->bolts->update(['is_active' => false]);

    expect(fn () => app(CreateOrder::class)->handle($this->user, $this->warehouse->id, [
        ['product_id' => $this->bolts->id, 'quantity' => 1],
    ]))->toThrow(Illuminate\Validation\ValidationException::class);
});
