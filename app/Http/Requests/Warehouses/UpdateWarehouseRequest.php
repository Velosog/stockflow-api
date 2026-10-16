<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use App\Http\Requests\Concerns\NormalizesCodes;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWarehouseRequest extends FormRequest
{
    use NormalizesCodes;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->route('warehouse');

        return [
            'code' => ['sometimes', 'string', 'regex:'.StoreWarehouseRequest::CODE_PATTERN, Rule::unique('warehouses', 'code')->ignore($warehouse)],
            'name' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The code may only contain letters, numbers and dashes (2 to 16 characters).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->uppercaseInput('code');
    }
}
