<?php

declare(strict_types=1);

use App\Actions\Stock\RecordStockMovement;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
| Feature tests boot the full application and run against PostgreSQL inside a
| transaction that is rolled back after each test (RefreshDatabase).
| Unit tests also extend the Laravel TestCase because actions use the container.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

// Concurrency tests spawn separate PHP processes, which cannot see data inside
// an uncommitted test transaction, so they commit and truncate instead.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->group('concurrency')
    ->in('Concurrency');

/**
 * A random password that satisfies the password policy. Generated at runtime
 * so no credential-looking literal is ever committed (gitleaks stays strict).
 */
function strongPassword(): string
{
    return 'Aa1-'.Str::password(16);
}

/**
 * Create a user with the given role and authenticate as them via Sanctum.
 */
function actingAsRole(Role $role): User
{
    $user = User::factory()->create(['role' => $role]);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * Receive goods through the real ledger, so levels and movements stay consistent.
 */
function receiveStock(Product $product, Warehouse $warehouse, int $quantity): StockLevel
{
    app(RecordStockMovement::class)->handle(
        User::factory()->create(),
        $product->id,
        $warehouse->id,
        StockMovementType::Inbound,
        $quantity,
    );

    return stockLevel($product, $warehouse);
}

function stockLevel(Product $product, Warehouse $warehouse): StockLevel
{
    return StockLevel::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->sole();
}

/**
 * Assert the response is an RFC 9457 problem details body with our extensions.
 *
 * @param  TestResponse<Symfony\Component\HttpFoundation\Response>  $response
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function assertProblem(TestResponse $response, int $status, string $code): TestResponse
{
    $response->assertStatus($status)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', $status)
        ->assertJsonPath('code', $code)
        ->assertJsonStructure(['title', 'status', 'code', 'detail', 'request_id']);

    return $response;
}
