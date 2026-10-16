<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Product;

function validProduct(array $overrides = []): array
{
    return [
        'sku' => 'hb-m8-100',
        'name' => 'Hex bolt M8 (100 pack)',
        'description' => 'Zinc plated.',
        'price_cents' => 1890,
        'low_stock_threshold' => 20,
        ...$overrides,
    ];
}

describe('reading', function (): void {
    it('lets every role list products with pagination', function (Role $role): void {
        actingAsRole($role);
        Product::factory()->count(3)->create();

        $this->getJson('/api/v1/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    })->with([Role::Admin, Role::Manager, Role::Operator]);

    it('filters by search term and active flag', function (): void {
        actingAsRole(Role::Operator);
        Product::factory()->create(['sku' => 'CB-CAT6-30', 'name' => 'Patch cable']);
        Product::factory()->create(['sku' => 'LT-LED-12W', 'name' => 'LED tube']);
        Product::factory()->inactive()->create(['sku' => 'CB-CAT5-10', 'name' => 'Old cable']);

        $this->getJson('/api/v1/products?search=cable')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/products?search=cable&is_active=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'CB-CAT6-30');
    });

    it('treats LIKE wildcards in the search term literally', function (): void {
        actingAsRole(Role::Operator);
        Product::factory()->create(['name' => 'Packing tape']);

        $this->getJson('/api/v1/products?search=%25%25')->assertOk()->assertJsonCount(0, 'data');
    });

    it('caps the page size', function (): void {
        actingAsRole(Role::Operator);

        assertProblem($this->getJson('/api/v1/products?per_page=500'), 422, 'validation_failed')
            ->assertJsonValidationErrors(['per_page']);
    });

    it('shows a product', function (): void {
        actingAsRole(Role::Operator);
        $product = Product::factory()->create();

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.sku', $product->sku)
            ->assertJsonPath('data.price_cents', $product->price_cents);
    });

    it('returns a generic 404 that does not leak the model class', function (): void {
        actingAsRole(Role::Operator);

        $response = assertProblem($this->getJson('/api/v1/products/999999'), 404, 'not_found');
        expect($response->getContent())->not->toContain('App\\\\Models');
    });
});

describe('writing', function (): void {
    it('lets catalog managers create products with a normalised SKU', function (Role $role): void {
        actingAsRole($role);

        $this->postJson('/api/v1/products', validProduct())
            ->assertCreated()
            ->assertJsonPath('data.sku', 'HB-M8-100')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('products', ['sku' => 'HB-M8-100', 'price_cents' => 1890]);
    })->with([Role::Admin, Role::Manager]);

    it('forbids operators from changing the catalog', function (): void {
        actingAsRole(Role::Operator);
        $product = Product::factory()->create();

        assertProblem($this->postJson('/api/v1/products', validProduct()), 403, 'forbidden');
        assertProblem($this->patchJson("/api/v1/products/{$product->id}", ['name' => 'x']), 403, 'forbidden');
        assertProblem($this->deleteJson("/api/v1/products/{$product->id}"), 403, 'forbidden');
        assertProblem($this->postJson("/api/v1/products/{$product->id}/restore"), 403, 'forbidden');
    });

    it('requires authentication', function (): void {
        assertProblem($this->postJson('/api/v1/products', validProduct()), 401, 'unauthenticated');
    });

    it('validates the payload', function (): void {
        actingAsRole(Role::Manager);

        assertProblem($this->postJson('/api/v1/products', validProduct([
            'sku' => 'bad sku!',
            'name' => '',
            'price_cents' => -1,
        ])), 422, 'validation_failed')
            ->assertJsonValidationErrors(['sku', 'name', 'price_cents']);
    });

    it('never reuses a SKU, even from a deleted product', function (): void {
        actingAsRole(Role::Manager);
        Product::factory()->create(['sku' => 'HB-M8-100'])->delete();

        assertProblem($this->postJson('/api/v1/products', validProduct()), 422, 'validation_failed')
            ->assertJsonValidationErrors(['sku']);
    });

    it('updates only the given fields', function (): void {
        actingAsRole(Role::Manager);
        $product = Product::factory()->create(['name' => 'Old name', 'price_cents' => 100]);

        $this->patchJson("/api/v1/products/{$product->id}", ['price_cents' => 250])
            ->assertOk()
            ->assertJsonPath('data.name', 'Old name')
            ->assertJsonPath('data.price_cents', 250);
    });

    it('soft-deletes and restores a product', function (): void {
        actingAsRole(Role::Manager);
        $product = Product::factory()->create();

        $this->deleteJson("/api/v1/products/{$product->id}")->assertNoContent();
        $this->assertSoftDeleted($product);
        $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();

        $this->postJson("/api/v1/products/{$product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.deleted_at', null);
        $this->assertNotSoftDeleted($product);
    });
});
