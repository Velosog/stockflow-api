<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * SKUs and warehouse codes are stored upper-case; normalising before
 * validation means " hb-m8 " and "HB-M8" hit the same unique rule.
 */
trait NormalizesCodes
{
    protected function uppercaseInput(string $key): void
    {
        $value = $this->input($key);

        if (is_string($value)) {
            $this->merge([$key => mb_strtoupper(trim($value))]);
        }
    }
}
