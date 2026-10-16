<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Http\Requests\Concerns\ValidatesPagination;
use Illuminate\Foundation\Http\FormRequest;

final class ListProductsRequest extends FormRequest
{
    use ValidatesPagination;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Matches SKU or name, case-insensitive.
            'search' => ['sometimes', 'string', 'min:2', 'max:100'],
            'is_active' => ['sometimes', 'in:true,false,1,0'],
            ...$this->paginationRules(),
        ];
    }
}
