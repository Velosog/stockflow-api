<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictional demo data for local development. All names, emails and places
 * are invented; emails use the reserved ".test" TLD.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must not run in production.');
        }

        // Local-only demo accounts. The password is documented in README.md.
        foreach ([Role::Admin, Role::Manager, Role::Operator] as $role) {
            User::factory()->create([
                'name' => ucfirst($role->value).' Demo',
                'email' => $role->value.'@stockflow.test',
                'role' => $role,
            ]);
        }

        collect([
            ['code' => 'WH-NORTH', 'name' => 'North Hub', 'city' => 'Riverton'],
            ['code' => 'WH-SOUTH', 'name' => 'South Hub', 'city' => 'Lakeside'],
            ['code' => 'WH-EAST', 'name' => 'East Depot', 'city' => 'Hillcrest'],
        ])->map(fn (array $attributes) => Warehouse::query()->create($attributes));

        $catalog = [
            ['sku' => 'HB-M8-100', 'name' => 'Hex bolt M8 (100 pack)', 'price_cents' => 1890, 'low_stock_threshold' => 20],
            ['sku' => 'WN-M8-100', 'name' => 'Wing nut M8 (100 pack)', 'price_cents' => 1240, 'low_stock_threshold' => 20],
            ['sku' => 'CB-CAT6-30', 'name' => 'Cat6 patch cable 30 m', 'price_cents' => 2450, 'low_stock_threshold' => 10],
            ['sku' => 'LT-LED-12W', 'name' => 'LED tube 12 W', 'price_cents' => 990, 'low_stock_threshold' => 50],
            ['sku' => 'GL-NIT-L', 'name' => 'Nitrile gloves, size L (box)', 'price_cents' => 1575, 'low_stock_threshold' => 30],
            ['sku' => 'TP-PKG-48', 'name' => 'Packing tape 48 mm', 'price_cents' => 345, 'low_stock_threshold' => 100],
            ['sku' => 'PL-WOOD-EU', 'name' => 'Wooden pallet (EUR size)', 'price_cents' => 4200, 'low_stock_threshold' => 5],
            ['sku' => 'SH-STEEL-5', 'name' => 'Steel shelving unit, 5 tiers', 'price_cents' => 18900, 'low_stock_threshold' => 2],
        ];

        foreach ($catalog as $attributes) {
            Product::query()->create($attributes);
        }

        Product::query()->create([
            'sku' => 'LG-LEGACY-01',
            'name' => 'Discontinued label printer',
            'price_cents' => 15900,
            'is_active' => false,
        ]);

    }
}
