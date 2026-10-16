<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordStockMovementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isAdjustment = $this->input('type') === StockMovementType::Adjustment->value;

        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            // reservation / release / outbound are created by orders only.
            'type' => ['required', Rule::in(StockMovementType::manualValues())],
            'quantity' => $isAdjustment
                ? ['required', 'integer', 'between:-1000000,1000000', 'not_in:0']
                : ['required', 'integer', 'min:1', 'max:1000000'],
            // Adjustments change counted stock, so they need an audit reason.
            'note' => [$isAdjustment ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    public function movementType(): StockMovementType
    {
        return StockMovementType::from($this->string('type')->toString());
    }
}
