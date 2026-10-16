<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use App\Http\Requests\Concerns\NormalizesCodes;
use Illuminate\Foundation\Http\FormRequest;

final class StoreWarehouseRequest extends FormRequest
{
    use NormalizesCodes;

    public const string CODE_PATTERN = '/\A[A-Z0-9][A-Z0-9-]{1,15}\z/';

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:'.self::CODE_PATTERN, 'unique:warehouses,code'],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
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
