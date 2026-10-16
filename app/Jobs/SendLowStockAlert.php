<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Role;
use App\Models\StockLevel;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies every manager that a product is running low in a warehouse.
 *
 * Dispatched after the stock transaction commits. Unique per stock level for
 * ten minutes, so a level bouncing around its threshold does not flood
 * managers with duplicate alerts.
 */
#[Tries(3)]
#[Backoff(10)]
#[UniqueFor(600)]
final class SendLowStockAlert implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $stockLevelId) {}

    public function uniqueId(): string
    {
        return (string) $this->stockLevelId;
    }

    public function handle(): void
    {
        $level = StockLevel::query()->with(['product', 'warehouse'])->find($this->stockLevelId);

        // The job runs later than the movement: stock may have been
        // replenished in the meantime, in which case there is nothing to say.
        if ($level === null || $level->product === null || $level->warehouse === null
            || $level->available >= $level->product->low_stock_threshold) {
            return;
        }

        $managers = User::query()->withRole(Role::Manager)->get();

        Notification::send($managers, new LowStockNotification(
            productId: $level->product->id,
            sku: $level->product->sku,
            productName: $level->product->name,
            warehouseId: $level->warehouse->id,
            warehouseCode: $level->warehouse->code,
            available: $level->available,
            threshold: $level->product->low_stock_threshold,
        ));
    }
}
