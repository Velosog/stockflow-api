<?php

declare(strict_types=1);

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use App\Http\Requests\Concerns\ValidatesPagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListOrdersRequest extends FormRequest
{
    use ValidatesPagination;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'warehouse_id' => ['sometimes', 'integer'],
            ...$this->paginationRules(),
        ];
    }
}
