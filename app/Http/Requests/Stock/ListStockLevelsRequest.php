<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Http\Requests\Concerns\ValidatesPagination;
use Illuminate\Foundation\Http\FormRequest;

final class ListStockLevelsRequest extends FormRequest
{
    use ValidatesPagination;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer'],
            'warehouse_id' => ['sometimes', 'integer'],
            // Only levels whose available stock is below the product threshold.
            'low_stock' => ['sometimes', 'in:true,false,1,0'],
            ...$this->paginationRules(),
        ];
    }
}
