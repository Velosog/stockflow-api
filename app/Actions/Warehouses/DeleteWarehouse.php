<?php

declare(strict_types=1);

namespace App\Actions\Warehouses;

use App\Exceptions\WarehouseInUseException;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class DeleteWarehouse
{
    /**
     * PostgreSQL SQLSTATEs raised when a row is still referenced:
     * foreign_key_violation and restrict_violation (ON DELETE RESTRICT).
     */
    private const array STILL_REFERENCED = ['23503', '23001'];

    public function handle(Warehouse $warehouse): void
    {
        // Let the foreign keys decide instead of checking first: a check-then-
        // delete would race with a movement inserted in between. The savepoint
        // (nested transaction) keeps any outer transaction usable on failure.
        try {
            DB::transaction(fn () => $warehouse->delete());
        } catch (QueryException $e) {
            if (in_array($e->getCode(), self::STILL_REFERENCED, true)) {
                throw new WarehouseInUseException;
            }

            throw $e;
        }
    }
}
