<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->product = Product::factory()->create(['low_stock_threshold' => 10]);
    $this->warehouse = Warehouse::factory()->create();
});

function movement(array $overrides = []): array
{
    return [
        'product_id' => test()->product->id,
        'warehouse_id' => test()->warehouse->id,
        'type' => 'inbound',
        'quantity' => 25,
        ...$overrides,
    ];
}

describe('recording movements', function (): void {
    it('lets every role record goods received', function (Role $role): void {
        $user = actingAsRole($role);

        $this->postJson('/api/v1/stock-movements', movement(['note' => 'PO-1001']))
            ->assertCreated()
            ->assertJsonPath('data.type', 'inbound')
            ->assertJsonPath('data.quantity', 25)
            ->assertJsonPath('data.on_hand_after', 25)
            ->assertJsonPath('data.performed_by', $user->id);

        expect(stockLevel($this->product, $this->warehouse)->available)->toBe(25);
    })->with([Role::Admin, Role::Manager, Role::Operator]);

    it('lets managers adjust stock with a reason', function (): void {
        actingAsRole(Role::Manager);
        receiveStock($this->product, $this->warehouse, 20);

        $this->postJson('/api/v1/stock-movements', movement(['type' => 'adjustment', 'quantity' => -3, 'note' => 'Damaged']))
            ->assertCreated()
            ->assertJsonPath('data.on_hand_after', 17);
    });

    it('forbids operators from adjusting stock', function (): void {
        actingAsRole(Role::Operator);

        assertProblem($this->postJson('/api/v1/stock-movements', movement(['type' => 'adjustment', 'quantity' => -1, 'note' => 'x'])), 403, 'forbidden');
    });

    it('requires a note for adjustments and rejects order-only types', function (): void {
        actingAsRole(Role::Manager);

        assertProblem($this->postJson('/api/v1/stock-movements', movement(['type' => 'adjustment', 'quantity' => -1])), 422, 'validation_failed')
            ->assertJsonValidationErrors(['note']);
        assertProblem($this->postJson('/api/v1/stock-movements', movement(['type' => 'reservation'])), 422, 'validation_failed')
            ->assertJsonValidationErrors(['type']);
        assertProblem($this->postJson('/api/v1/stock-movements', movement(['quantity' => 0])), 422, 'validation_failed')
            ->assertJsonValidationErrors(['quantity']);
    });

    it('refuses an adjustment that would cut into reserved stock', function (): void {
        actingAsRole(Role::Manager);
        $level = receiveStock($this->product, $this->warehouse, 5);
        $level->forceFill(['reserved' => 4])->save();

        assertProblem($this->postJson('/api/v1/stock-movements', movement(['type' => 'adjustment', 'quantity' => -2, 'note' => 'Count'])), 409, 'stock_adjustment_conflict')
            ->assertJsonPath('reserved', 4);
    });

    it('requires authentication', function (): void {
        assertProblem($this->postJson('/api/v1/stock-movements', movement()), 401, 'unauthenticated');
    });
});

describe('reading stock', function (): void {
    it('lists stock levels with product and warehouse details', function (): void {
        actingAsRole(Role::Operator);
        receiveStock($this->product, $this->warehouse, 30);

        $this->getJson('/api/v1/stock-levels')
            ->assertOk()
            ->assertJsonPath('data.0.product.sku', $this->product->sku)
            ->assertJsonPath('data.0.warehouse.code', $this->warehouse->code)
            ->assertJsonPath('data.0.on_hand', 30)
            ->assertJsonPath('data.0.available', 30);
    });

    it('filters levels below the low-stock threshold', function (): void {
        actingAsRole(Role::Operator);
        receiveStock($this->product, $this->warehouse, 4);
        $plenty = Product::factory()->create(['low_stock_threshold' => 10]);
        receiveStock($plenty, $this->warehouse, 50);

        $this->getJson('/api/v1/stock-levels?low_stock=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.id', $this->product->id);
    });

    it('lists the ledger newest first with filters', function (): void {
        actingAsRole(Role::Operator);
        receiveStock($this->product, $this->warehouse, 5);
        receiveStock($this->product, $this->warehouse, 7);
        receiveStock(Product::factory()->create(), $this->warehouse, 1);

        $this->getJson("/api/v1/stock-movements?product_id={$this->product->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.quantity', 7);

        expect(StockMovement::query()->count())->toBe(3);
    });
});

it('refuses to delete a warehouse that has stock history', function (): void {
    actingAsRole(Role::Admin);
    receiveStock($this->product, $this->warehouse, 1);

    assertProblem($this->deleteJson("/api/v1/warehouses/{$this->warehouse->id}"), 409, 'warehouse_in_use');
    $this->assertDatabaseHas('warehouses', ['id' => $this->warehouse->id]);
});
