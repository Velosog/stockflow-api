<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * For read-side tests only. Code paths that change stock must go through
 * App\Services\StockLedger so the ledger stays consistent.
 *
 * @extends Factory<StockLevel>
 */
class StockLevelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'on_hand' => fake()->numberBetween(0, 500),
            'reserved' => 0,
        ];
    }
}
