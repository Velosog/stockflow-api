<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StockLevelController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
| Every route except login requires a Sanctum bearer token and is authorized
| by a policy (see the #[Authorize] attributes on each controller method).
| "throttle:api" applies to all authenticated traffic; "throttle:writes" is an
| extra, stricter limit on mutating requests.
*/

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->middleware('throttle:writes')->name('users.store');

        Route::post('products/{product}/restore', [ProductController::class, 'restore'])
            ->withTrashed()
            ->middleware('throttle:writes')
            ->name('products.restore');
        Route::apiResource('products', ProductController::class)
            ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

        Route::apiResource('warehouses', WarehouseController::class)
            ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

        Route::get('stock-levels', [StockLevelController::class, 'index'])->name('stock-levels.index');
        Route::get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
        Route::post('stock-movements', [StockMovementController::class, 'store'])
            ->middleware('throttle:writes')
            ->name('stock-movements.store');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders', [OrderController::class, 'store'])
            ->middleware(['throttle:writes', 'idempotent'])
            ->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])
            ->middleware('throttle:writes')
            ->name('orders.confirm');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->middleware('throttle:writes')
            ->name('orders.cancel');
    });
});
