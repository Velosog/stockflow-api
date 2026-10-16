<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Http\Requests\Concerns\ValidatesPagination;
use Illuminate\Foundation\Http\FormRequest;

final class ListNotificationsRequest extends FormRequest
{
    use ValidatesPagination;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'unread' => ['sometimes', 'in:true,false,1,0'],
            ...$this->paginationRules(),
        ];
    }
}
