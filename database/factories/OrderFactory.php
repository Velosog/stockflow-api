<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An order without items or reservations, for read-side and policy tests.
 * Real orders are created through App\Actions\Orders\CreateOrder.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'created_by' => User::factory(),
            'status' => OrderStatus::Pending,
            'customer_reference' => strtoupper(fake()->bothify('CUST-####')),
            'total_cents' => 0,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => OrderStatus::Confirmed, 'confirmed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
    }
}
