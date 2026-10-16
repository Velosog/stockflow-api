<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property int $price_cents
 * @property int $low_stock_threshold
 * @property bool $is_active
 */
#[Fillable(['sku', 'name', 'description', 'price_cents', 'low_stock_threshold', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Mirrors the column defaults so a freshly created model is complete
     * without a refresh() round-trip.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'low_stock_threshold' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Case-insensitive match on SKU or name. LIKE wildcards in the term are
     * escaped so user input cannot turn into an expensive pattern.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $escaped = '%'.addcslashes($term, '\\%_').'%';

        $query->where(function (Builder $query) use ($escaped): void {
            $query->whereLike('sku', $escaped)->orWhereLike('name', $escaped);
        });
    }
}
