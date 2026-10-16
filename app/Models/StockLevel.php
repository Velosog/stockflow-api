<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StockLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current quantities of one product in one warehouse. Only App\Services\StockLedger
 * writes to it; `available` is a generated column (on_hand - reserved).
 *
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $on_hand
 * @property int $reserved
 * @property int $available
 */
#[Fillable(['product_id', 'warehouse_id', 'on_hand', 'reserved'])]
class StockLevel extends Model
{
    /** @use HasFactory<StockLevelFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'on_hand' => 0,
        'reserved' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'available' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        // History stays readable after a product is soft-deleted.
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
