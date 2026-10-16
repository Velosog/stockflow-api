<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->user = actingAsRole(Role::Operator);
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create();
    receiveStock($this->product, $this->warehouse, 10);
});

function idempotentOrder(int $quantity = 2): array
{
    return [
        'warehouse_id' => test()->warehouse->id,
        'items' => [['product_id' => test()->product->id, 'quantity' => $quantity]],
    ];
}

it('replays the original response for a repeated key without creating a second order', function (): void {
    $headers = ['Idempotency-Key' => 'order-7f3c2a'];

    $first = $this->postJson('/api/v1/orders', idempotentOrder(), $headers)->assertCreated();
    $second = $this->postJson('/api/v1/orders', idempotentOrder(), $headers)
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json())->toBe($first->json())
        ->and(Order::query()->count())->toBe(1)
        ->and(stockLevel($this->product, $this->warehouse)->reserved)->toBe(2);
});

it('treats a body with keys in a different order as the same request', function (): void {
    $headers = ['Idempotency-Key' => 'order-key-order'];

    $this->postJson('/api/v1/orders', idempotentOrder(), $headers)->assertCreated();
    $this->postJson('/api/v1/orders', array_reverse(idempotentOrder(), true), $headers)
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect(Order::query()->count())->toBe(1);
});

it('rejects a reused key with a different body', function (): void {
    $headers = ['Idempotency-Key' => 'order-reused'];

    $this->postJson('/api/v1/orders', idempotentOrder(2), $headers)->assertCreated();

    assertProblem($this->postJson('/api/v1/orders', idempotentOrder(3), $headers), 422, 'idempotency_key_reused');
    expect(Order::query()->count())->toBe(1);
});

it('does not store failures, so the same key can be retried', function (): void {
    $headers = ['Idempotency-Key' => 'order-retry-after-restock'];

    assertProblem($this->postJson('/api/v1/orders', idempotentOrder(15), $headers), 409, 'insufficient_stock');
    expect(IdempotencyKey::query()->count())->toBe(0);

    receiveStock($this->product, $this->warehouse, 10);

    $this->postJson('/api/v1/orders', idempotentOrder(15), $headers)
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed');
});

it('answers 409 while the first request with the key is still running', function (): void {
    IdempotencyKey::query()->create([
        'user_id' => $this->user->id,
        'key' => 'order-in-flight',
        'request_fingerprint' => str_repeat('0', 64),
        'expires_at' => now()->addDay(),
    ]);

    assertProblem($this->postJson('/api/v1/orders', idempotentOrder(), ['Idempotency-Key' => 'order-in-flight']), 409, 'idempotency_request_in_progress');
    expect(Order::query()->count())->toBe(0);
});

it('scopes keys per user', function (): void {
    $headers = ['Idempotency-Key' => 'shared-key-name'];
    $this->postJson('/api/v1/orders', idempotentOrder(), $headers)->assertCreated();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/orders', idempotentOrder(), $headers)
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed');

    expect(Order::query()->count())->toBe(2);
});

it('accepts an expired key as new', function (): void {
    $headers = ['Idempotency-Key' => 'order-expired'];
    $this->postJson('/api/v1/orders', idempotentOrder(), $headers)->assertCreated();

    $this->travel(25)->hours();

    $this->postJson('/api/v1/orders', idempotentOrder(), $headers)
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed');
    expect(Order::query()->count())->toBe(2);
});

it('rejects a malformed key', function (): void {
    assertProblem($this->postJson('/api/v1/orders', idempotentOrder(), ['Idempotency-Key' => str_repeat('k', 256)]), 400, 'invalid_idempotency_key');
});

it('works without the header (plain non-idempotent create)', function (): void {
    $this->postJson('/api/v1/orders', idempotentOrder())->assertCreated();
    $this->postJson('/api/v1/orders', idempotentOrder())->assertCreated();

    expect(Order::query()->count())->toBe(2)
        ->and(IdempotencyKey::query()->count())->toBe(0);
});

it('prunes expired keys', function (): void {
    $this->postJson('/api/v1/orders', idempotentOrder(), ['Idempotency-Key' => 'order-to-prune'])->assertCreated();
    $this->travel(25)->hours();

    $this->artisan('model:prune', ['--model' => [IdempotencyKey::class]])->assertSuccessful();

    expect(IdempotencyKey::query()->count())->toBe(0);
});
