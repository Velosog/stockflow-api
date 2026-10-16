<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Http\Requests\Concerns\NormalizesCodes;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends FormRequest
{
    use NormalizesCodes;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'sku' => ['sometimes', 'string', 'regex:'.StoreProductRequest::SKU_PATTERN, Rule::unique('products', 'sku')->ignore($product)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price_cents' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'The SKU may only contain letters, numbers and dashes (2 to 64 characters).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->uppercaseInput('sku');
    }
}
