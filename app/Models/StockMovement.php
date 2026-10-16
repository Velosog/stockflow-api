<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable ledger entry. A database trigger rejects UPDATE and DELETE.
 *
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property int $on_hand_after
 * @property int $reserved_after
 * @property int|null $order_id
 * @property int|null $performed_by
 * @property string|null $note
 */
#[Fillable([
    'product_id', 'warehouse_id', 'type', 'quantity', 'on_hand_after', 'reserved_after',
    'order_id', 'performed_by', 'note',
])]
class StockMovement extends Model
{
    /** Append-only: there is no updated_at column. */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'on_hand_after' => 'integer',
            'reserved_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
