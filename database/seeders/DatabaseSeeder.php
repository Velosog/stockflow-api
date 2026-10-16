<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Stock\RecordStockMovement;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictional demo data for local development. All names, emails and places
 * are invented; emails use the reserved ".test" TLD. Stock is created through
 * the same actions the API uses, so the ledger and levels are consistent.
 */
class DatabaseSeeder extends Seeder
{
    public function run(
        RecordStockMovement $recordStockMovement,
        CreateOrder $createOrder,
        ConfirmOrder $confirmOrder,
        CancelOrder $cancelOrder,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must not run in production.');
        }

        // Local-only demo accounts. The password is documented in README.md.
        $users = [];
        foreach ([Role::Admin, Role::Manager, Role::Operator] as $role) {
            $users[$role->value] = User::factory()->create([
                'name' => ucfirst($role->value).' Demo',
                'email' => $role->value.'@stockflow.test',
                'role' => $role,
            ]);
        }

        $warehouses = array_map(fn (array $attributes): Warehouse => Warehouse::query()->create($attributes), [
            ['code' => 'WH-NORTH', 'name' => 'North Hub', 'city' => 'Riverton'],
            ['code' => 'WH-SOUTH', 'name' => 'South Hub', 'city' => 'Lakeside'],
            ['code' => 'WH-EAST', 'name' => 'East Depot', 'city' => 'Hillcrest'],
        ]);

        $catalog = array_map(fn (array $attributes): Product => Product::query()->create($attributes), [
            ['sku' => 'HB-M8-100', 'name' => 'Hex bolt M8 (100 pack)', 'price_cents' => 1890, 'low_stock_threshold' => 20],
            ['sku' => 'WN-M8-100', 'name' => 'Wing nut M8 (100 pack)', 'price_cents' => 1240, 'low_stock_threshold' => 20],
            ['sku' => 'CB-CAT6-30', 'name' => 'Cat6 patch cable 30 m', 'price_cents' => 2450, 'low_stock_threshold' => 10],
            ['sku' => 'LT-LED-12W', 'name' => 'LED tube 12 W', 'price_cents' => 990, 'low_stock_threshold' => 50],
            ['sku' => 'GL-NIT-L', 'name' => 'Nitrile gloves, size L (box)', 'price_cents' => 1575, 'low_stock_threshold' => 30],
            ['sku' => 'TP-PKG-48', 'name' => 'Packing tape 48 mm', 'price_cents' => 345, 'low_stock_threshold' => 100],
            ['sku' => 'PL-WOOD-EU', 'name' => 'Wooden pallet (EUR size)', 'price_cents' => 4200, 'low_stock_threshold' => 5],
            ['sku' => 'SH-STEEL-5', 'name' => 'Steel shelving unit, 5 tiers', 'price_cents' => 18900, 'low_stock_threshold' => 2],
        ]);

        Product::query()->create([
            'sku' => 'LG-LEGACY-01',
            'name' => 'Discontinued label printer',
            'price_cents' => 15900,
            'is_active' => false,
        ]);

        // Goods received in every warehouse (deterministic quantities).
        foreach ($warehouses as $w => $warehouse) {
            foreach ($catalog as $p => $product) {
                $recordStockMovement->handle(
                    $users['operator'],
                    $product->id,
                    $warehouse->id,
                    StockMovementType::Inbound,
                    40 + (($w + 1) * ($p + 3) * 7) % 160,
                    'Initial stock',
                );
            }
        }

        // A stock count correction.
        $recordStockMovement->handle(
            $users['manager'],
            $catalog[0]->id,
            $warehouses[0]->id,
            StockMovementType::Adjustment,
            -3,
            'Cycle count: 3 packs damaged',
        );

        // Orders in each status.
        $createOrder->handle($users['operator'], $warehouses[0]->id, [
            ['product_id' => $catalog[0]->id, 'quantity' => 10],
            ['product_id' => $catalog[2]->id, 'quantity' => 4],
        ], 'CUST-0001');

        $confirmed = $createOrder->handle($users['operator'], $warehouses[1]->id, [
            ['product_id' => $catalog[3]->id, 'quantity' => 25],
        ], 'CUST-0002');
        $confirmOrder->handle($confirmed, $users['manager']);

        $cancelled = $createOrder->handle($users['operator'], $warehouses[2]->id, [
            ['product_id' => $catalog[5]->id, 'quantity' => 12],
        ], 'CUST-0003');
        $cancelOrder->handle($cancelled, $users['manager'], 'Customer changed the delivery date');
    }
}
