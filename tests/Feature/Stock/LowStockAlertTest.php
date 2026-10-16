<?php

declare(strict_types=1);

use App\Actions\Orders\CreateOrder;
use App\Actions\Stock\RecordStockMovement;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Jobs\SendLowStockAlert;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->warehouse = Warehouse::factory()->create(['code' => 'WH-NORTH']);
    $this->product = Product::factory()->create(['sku' => 'HB-M8-100', 'low_stock_threshold' => 10]);
    $this->operator = User::factory()->operator()->create();
    $this->level = receiveStock($this->product, $this->warehouse, 12);
});

function reserve(int $quantity): void
{
    app(CreateOrder::class)->handle(test()->operator, test()->warehouse->id, [
        ['product_id' => test()->product->id, 'quantity' => $quantity],
    ]);
}

describe('dispatching', function (): void {
    beforeEach(fn () => Queue::fake());

    it('queues an alert when available stock crosses below the threshold', function (): void {
        reserve(3); // 12 -> 9 available, threshold 10

        Queue::assertPushed(SendLowStockAlert::class, fn (SendLowStockAlert $job) => $job->stockLevelId === $this->level->id);
    });

    it('does not alert while stock stays at or above the threshold', function (): void {
        reserve(2); // 12 -> 10

        Queue::assertNotPushed(SendLowStockAlert::class);
    });

    it('alerts once per crossing, not on every movement while already low', function (): void {
        reserve(3); // 12 -> 9: crosses
        reserve(1); // 9 -> 8: already below

        Queue::assertPushed(SendLowStockAlert::class, 1);
    });

    it('also alerts on a negative adjustment that crosses the threshold', function (): void {
        app(RecordStockMovement::class)->handle(
            User::factory()->manager()->create(),
            $this->product->id,
            $this->warehouse->id,
            StockMovementType::Adjustment,
            -5,
            'Damaged in transit',
        );

        Queue::assertPushed(SendLowStockAlert::class);
    });

    it('does not alert when the reservation fails and rolls back', function (): void {
        expect(fn () => reserve(50))->toThrow(App\Exceptions\InsufficientStockException::class);

        Queue::assertNotPushed(SendLowStockAlert::class);
    });

    it('is a queued, unique job', function (): void {
        expect(new SendLowStockAlert(1))
            ->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class)
            ->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldBeUnique::class);
    });
});

describe('handling', function (): void {
    it('notifies every manager, and only managers, through the database channel', function (): void {
        $managers = User::factory()->manager()->count(2)->create();
        $admin = User::factory()->admin()->create();
        reserve(3); // the sync queue (tests) runs the job right after commit

        foreach ($managers as $manager) {
            $notification = $manager->notifications()->sole();
            expect($notification->type)->toBe('low_stock')
                ->and($notification->data)->toMatchArray([
                    'sku' => 'HB-M8-100',
                    'warehouse_code' => 'WH-NORTH',
                    'available' => 9,
                    'threshold' => 10,
                ]);
        }

        expect($admin->notifications()->count())->toBe(0)
            ->and($this->operator->notifications()->count())->toBe(0);
    });

    it('does nothing if stock was replenished before the job ran', function (): void {
        $manager = User::factory()->manager()->create();
        Queue::fake();
        reserve(3);
        receiveStock($this->product, $this->warehouse, 20);

        (new SendLowStockAlert($this->level->id))->handle();

        expect($manager->notifications()->count())->toBe(0);
    });

    it('lets a manager read the alert through the API', function (): void {
        $manager = User::factory()->manager()->create();
        reserve(3);

        Laravel\Sanctum\Sanctum::actingAs($manager);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'low_stock')
            ->assertJsonPath('data.0.payload.sku', 'HB-M8-100');
    });
});

it('routes low-stock alerts to managers according to the role matrix', function (): void {
    expect(Role::Manager->receivesLowStockAlerts())->toBeTrue();
});
