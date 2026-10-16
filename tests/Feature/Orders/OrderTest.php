<?php

declare(strict_types=1);

use App\Actions\Orders\CreateOrder;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['price_cents' => 1500]);
    receiveStock($this->product, $this->warehouse, 10);
});

function orderPayload(array $overrides = []): array
{
    return [
        'warehouse_id' => test()->warehouse->id,
        'customer_reference' => 'CUST-0007',
        'items' => [['product_id' => test()->product->id, 'quantity' => 3]],
        ...$overrides,
    ];
}

function placeOrder(User $by, int $quantity = 3): Order
{
    return app(CreateOrder::class)->handle($by, test()->warehouse->id, [
        ['product_id' => test()->product->id, 'quantity' => $quantity],
    ]);
}

describe('creating', function (): void {
    it('creates a pending order and reserves stock', function (): void {
        $user = actingAsRole(Role::Operator);

        $this->postJson('/api/v1/orders', orderPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_cents', 4500)
            ->assertJsonPath('data.created_by', $user->id)
            ->assertJsonPath('data.items.0.sku', $this->product->sku)
            ->assertJsonPath('data.items.0.quantity', 3);

        $level = stockLevel($this->product, $this->warehouse);
        expect([$level->on_hand, $level->reserved, $level->available])->toBe([10, 3, 7]);
    });

    it('answers 409 with the shortages and reserves nothing', function (): void {
        actingAsRole(Role::Operator);

        assertProblem($this->postJson('/api/v1/orders', orderPayload([
            'items' => [['product_id' => $this->product->id, 'quantity' => 11]],
        ])), 409, 'insufficient_stock')
            ->assertJsonPath('shortages.0.requested', 11)
            ->assertJsonPath('shortages.0.available', 10);

        expect(Order::query()->count())->toBe(0)
            ->and(stockLevel($this->product, $this->warehouse)->reserved)->toBe(0);
    });

    it('validates items', function (array $items, string $error): void {
        actingAsRole(Role::Operator);

        assertProblem($this->postJson('/api/v1/orders', orderPayload(['items' => $items])), 422, 'validation_failed')
            ->assertJsonValidationErrors([$error]);
    })->with([
        'empty' => [[], 'items'],
        'duplicate product' => [fn () => [
            ['product_id' => test()->product->id, 'quantity' => 1],
            ['product_id' => test()->product->id, 'quantity' => 2],
        ], 'items.1.product_id'],
        'zero quantity' => [fn () => [['product_id' => test()->product->id, 'quantity' => 0]], 'items.0.quantity'],
        'unknown product' => [[['product_id' => 999999, 'quantity' => 1]], 'items.0.product_id'],
        'inactive product' => [fn () => [['product_id' => Product::factory()->inactive()->create()->id, 'quantity' => 1]], 'items.0.product_id'],
    ]);

    it('requires authentication', function (): void {
        assertProblem($this->postJson('/api/v1/orders', orderPayload()), 401, 'unauthenticated');
    });
});

describe('reading', function (): void {
    it('lists orders filtered by status and shows one', function (): void {
        $user = actingAsRole(Role::Operator);
        $order = placeOrder($user);
        Order::factory()->cancelled()->create(['warehouse_id' => $this->warehouse->id]);

        $this->getJson('/api/v1/orders?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_id', $this->product->id);
    });
});

describe('confirming and cancelling', function (): void {
    it('lets a manager confirm a pending order', function (): void {
        $order = placeOrder(User::factory()->create());
        actingAsRole(Role::Manager);

        $this->postJson("/api/v1/orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $level = stockLevel($this->product, $this->warehouse);
        expect([$level->on_hand, $level->reserved])->toBe([7, 0]);
    });

    it('forbids operators from confirming orders', function (): void {
        $order = placeOrder(actingAsRole(Role::Operator));

        assertProblem($this->postJson("/api/v1/orders/{$order->id}/confirm"), 403, 'forbidden');
    });

    it('lets operators cancel their own orders only', function (): void {
        $operator = actingAsRole(Role::Operator);
        $own = placeOrder($operator, 2);
        $someoneElses = placeOrder(User::factory()->create(), 2);

        $this->postJson("/api/v1/orders/{$own->id}/cancel", ['reason' => 'Duplicate'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Duplicate');

        assertProblem($this->postJson("/api/v1/orders/{$someoneElses->id}/cancel"), 403, 'forbidden');
    });

    it('answers 409 for an invalid status transition', function (): void {
        $order = placeOrder(User::factory()->create());
        actingAsRole(Role::Manager);
        $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();

        assertProblem($this->postJson("/api/v1/orders/{$order->id}/confirm"), 409, 'invalid_status_transition')
            ->assertJsonPath('current_status', 'cancelled')
            ->assertJsonPath('requested_status', 'confirmed');
    });

    it('answers 404 for an unknown order', function (): void {
        actingAsRole(Role::Manager);

        assertProblem($this->postJson('/api/v1/orders/999999/confirm'), 404, 'not_found');
    });
});
