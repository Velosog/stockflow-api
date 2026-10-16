<?php

declare(strict_types=1);

/*
 * Child process for the concurrency tests: a separate PHP process with its
 * own database connection, exactly like a separate API request.
 *
 * It boots the application, opens its DB connection, signals "ready" and
 * waits for the shared "go" file, so that every worker calls CreateOrder at
 * the same moment. It prints the outcome as one JSON line.
 *
 * Usage: php create_order_worker.php <userId> <warehouseId> <productId> <quantity> <barrierDir>
 */

use App\Actions\Orders\CreateOrder;
use App\Exceptions\InsufficientStockException;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $userId, $warehouseId, $productId, $quantity, $barrier] = $argv;

// Opens the database connection before the race starts.
$user = User::query()->findOrFail((int) $userId);

touch($barrier.'/ready-'.getmypid());
while (! file_exists($barrier.'/go')) {
    usleep(200);
}

try {
    $order = $app->make(CreateOrder::class)->handle($user, (int) $warehouseId, [
        ['product_id' => (int) $productId, 'quantity' => (int) $quantity],
    ]);
    $result = ['outcome' => 'created', 'order_id' => $order->id];
} catch (InsufficientStockException) {
    $result = ['outcome' => 'insufficient_stock'];
} catch (Throwable $e) {
    // Deadlocks, lock timeouts or constraint violations would show up here.
    $result = ['outcome' => 'error', 'error' => $e::class.': '.$e->getMessage()];
}

echo json_encode($result), PHP_EOL;
