<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a pending order and reserves stock for every line, atomically:
 * either all lines are reserved or nothing is written.
 */
final class CreateOrder
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines  one line per product
     */
    public function handle(User $createdBy, int $warehouseId, array $lines, ?string $customerReference = null): Order
    {
        $productIds = array_map(fn (array $line): int => $line['product_id'], $lines);

        return DB::transaction(function () use ($createdBy, $warehouseId, $lines, $productIds, $customerReference): Order {
            $products = Product::query()->active()->whereKey($productIds)->get()->keyBy('id');

            // Row locks, in product_id order (see StockLedger::lockLevels).
            $levels = $this->ledger->lockLevels($warehouseId, $productIds);

            // Check every line before writing anything so the client learns
            // about all shortages at once.
            /** @var list<array{product: Product, level: StockLevel, quantity: int}> $reservations */
            $reservations = [];
            $shortages = [];

            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);
                if ($product === null) {
                    // Deactivated or deleted after request validation ran.
                    throw ValidationException::withMessages(['items' => 'One or more products are no longer available.']);
                }

                $level = $levels->get($product->id);
                if ($level === null || $level->available < $line['quantity']) {
                    $shortages[] = [
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'requested' => $line['quantity'],
                        'available' => $level->available ?? 0,
                    ];

                    continue;
                }

                $reservations[] = ['product' => $product, 'level' => $level, 'quantity' => $line['quantity']];
            }

            if ($shortages !== []) {
                throw new InsufficientStockException($shortages);
            }

            $order = Order::query()->create([
                'warehouse_id' => $warehouseId,
                'created_by' => $createdBy->id,
                'status' => OrderStatus::Pending,
                'customer_reference' => $customerReference,
                'total_cents' => array_sum(array_map(
                    fn (array $reservation): int => $reservation['quantity'] * $reservation['product']->price_cents,
                    $reservations,
                )),
            ]);

            foreach ($reservations as ['product' => $product, 'level' => $level, 'quantity' => $quantity]) {
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    // Price snapshot taken at order time.
                    'unit_price_cents' => $product->price_cents,
                    'line_total_cents' => $quantity * $product->price_cents,
                ]);

                $this->ledger->record($level, StockMovementType::Reservation, $quantity, order: $order, performedBy: $createdBy);
            }

            return $order;
        });
    }
}
