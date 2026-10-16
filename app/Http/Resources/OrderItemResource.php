<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'sku' => $this->whenLoaded('product', fn (Product $product): string => $product->sku),
            'name' => $this->whenLoaded('product', fn (Product $product): string => $product->name),
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'line_total_cents' => $this->line_total_cents,
        ];
    }
}
