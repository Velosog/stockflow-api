<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Current quantities per product per warehouse: a denormalised projection of
 * the stock_movements ledger, updated in the same transaction as each
 * movement. Reservations lock these rows (SELECT ... FOR UPDATE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            // Computed by PostgreSQL, so it can never drift from the other two.
            $table->integer('available')->storedAs('on_hand - reserved');
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'available']);
        });

        // Last line of defence: even a buggy code path cannot oversell.
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT stock_levels_on_hand_check CHECK (on_hand >= 0)');
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT stock_levels_reserved_check CHECK (reserved >= 0 AND reserved <= on_hand)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
