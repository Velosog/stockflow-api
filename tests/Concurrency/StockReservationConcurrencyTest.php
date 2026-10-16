<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/*
| These tests start real, separate PHP processes that each try to reserve
| stock at the same instant. Test data is therefore committed (no wrapping
| transaction), and every table is truncated afterwards.
*/

afterEach(function (): void {
    $this->truncateTablesForAllConnections();
});

/**
 * Start $workers processes that each try to order $quantity units at once.
 *
 * @return list<array{outcome: string, order_id?: int, error?: string}>
 */
function raceToReserve(User $user, Warehouse $warehouse, Product $product, int $quantity, int $workers): array
{
    $barrier = storage_path('framework/testing/barrier-'.Str::uuid());
    File::ensureDirectoryExists($barrier);

    $connection = config('database.connections.pgsql');
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) $connection['password'],
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
    $command = [
        PHP_BINARY,
        base_path('tests/Concurrency/Support/create_order_worker.php'),
        (string) $user->id, (string) $warehouse->id, (string) $product->id, (string) $quantity, $barrier,
    ];

    try {
        $pool = Process::pool(function (Pool $pool) use ($workers, $env, $command): void {
            for ($i = 0; $i < $workers; $i++) {
                $pool->as("worker-{$i}")->env($env)->timeout(120)->command($command);
            }
        })->start();

        // Wait until every worker has booted and connected...
        $deadline = microtime(true) + 90;
        while (count(glob($barrier.'/ready-*') ?: []) < $workers) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not become ready in time.');
            }
            usleep(10_000);
        }

        // ...then release all of them at once.
        touch($barrier.'/go');

        return $pool->wait()->collect()
            ->map(fn ($result): array => json_decode(trim($result->output()), true)
                ?? ['outcome' => 'crashed', 'error' => $result->errorOutput()])
            ->values()
            ->all();
    } finally {
        File::deleteDirectory($barrier);
    }
}

function outcomes(array $results): array
{
    $counts = array_count_values(array_column($results, 'outcome'));
    ksort($counts);

    return $counts;
}

it('never oversells when many requests race for the last units', function (): void {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $user = User::factory()->create();
    receiveStock($product, $warehouse, 5);

    $results = raceToReserve($user, $warehouse, $product, quantity: 1, workers: 12);

    // Exactly 5 succeed and every other request fails cleanly: no deadlocks,
    // no constraint violations, no lost updates.
    expect(outcomes($results))->toBe(['created' => 5, 'insufficient_stock' => 7]);

    $level = stockLevel($product, $warehouse);
    expect([$level->on_hand, $level->reserved, $level->available])->toBe([5, 5, 0])
        ->and(Order::query()->count())->toBe(5)
        ->and((int) StockMovement::query()->where('type', StockMovementType::Reservation)->sum('quantity'))->toBe(5);
});

it('never oversells multi-unit orders', function (): void {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $user = User::factory()->create();
    receiveStock($product, $warehouse, 7);

    $results = raceToReserve($user, $warehouse, $product, quantity: 2, workers: 10);

    expect(outcomes($results))->toBe(['created' => 3, 'insufficient_stock' => 7]);

    $level = stockLevel($product, $warehouse);
    expect([$level->on_hand, $level->reserved, $level->available])->toBe([7, 6, 1]);

    // The ledger and the denormalised level agree.
    expect((int) StockMovement::query()->where('type', StockMovementType::Reservation)->sum('quantity'))->toBe($level->reserved);
});
