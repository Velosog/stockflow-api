<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Http\Requests\Concerns\NormalizesCodes;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProductRequest extends FormRequest
{
    use NormalizesCodes;

    public const string SKU_PATTERN = '/\A[A-Z0-9][A-Z0-9-]{1,63}\z/';

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'regex:'.self::SKU_PATTERN, 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
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
