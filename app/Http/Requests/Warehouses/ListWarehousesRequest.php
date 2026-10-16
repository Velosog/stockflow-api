<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use App\Http\Requests\Concerns\ValidatesPagination;
use Illuminate\Foundation\Http\FormRequest;

final class ListWarehousesRequest extends FormRequest
{
    use ValidatesPagination;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'city' => ['sometimes', 'string', 'max:120'],
            ...$this->paginationRules(),
        ];
    }
}
