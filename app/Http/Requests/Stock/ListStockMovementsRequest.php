<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Enums\StockMovementType;
use App\Http\Requests\Concerns\ValidatesPagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListStockMovementsRequest extends FormRequest
{
    use ValidatesPagination;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer'],
            'warehouse_id' => ['sometimes', 'integer'],
            'order_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', Rule::enum(StockMovementType::class)],
            ...$this->paginationRules(),
        ];
    }
}
