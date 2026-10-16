<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockLevel
 */
final class StockLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'low_stock_threshold' => $product->low_stock_threshold,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
            ]),
            'on_hand' => $this->on_hand,
            'reserved' => $this->reserved,
            'available' => $this->available,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
