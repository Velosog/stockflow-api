<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Facades\Config;

/**
 * Shared "per_page" handling for list endpoints, capped so a client cannot
 * request an arbitrarily large page.
 */
trait ValidatesPagination
{
    /**
     * @return array<string, list<string>>
     */
    protected function paginationRules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.Config::integer('stockflow.pagination.max_per_page')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', Config::integer('stockflow.pagination.default_per_page'));
    }
}
