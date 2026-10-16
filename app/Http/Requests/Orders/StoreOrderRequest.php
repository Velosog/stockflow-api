<?php

declare(strict_types=1);

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'customer_reference' => ['nullable', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            // One line per product: quantities must be merged by the client.
            'items.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.product_id.exists' => 'The product does not exist or is not active.',
            'items.*.product_id.distinct' => 'Each product may appear only once per order.',
        ];
    }

    /**
     * @return list<array{product_id: int, quantity: int}>
     */
    public function lines(): array
    {
        /** @var list<array{product_id: int|string, quantity: int|string}> $items */
        $items = $this->validated('items');

        return array_map(fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
        ], $items);
    }
}
