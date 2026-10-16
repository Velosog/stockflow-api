<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
final class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'quantity' => $this->quantity,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'order_id' => $this->order_id,
            'performed_by' => $this->performed_by,
            'note' => $this->note,
            'on_hand_after' => $this->on_hand_after,
            'reserved_after' => $this->reserved_after,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
