<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger: the source of truth for every stock change. Rows are
 * never updated or deleted; corrections are new "adjustment" rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            // Positive for every type except adjustments, which may be negative.
            $table->integer('quantity');
            // Balances right after this movement, for auditing and reconciliation.
            $table->integer('on_hand_after');
            $table->integer('reserved_after');
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'warehouse_id', 'created_at']);
            $table->index('order_id');
            $table->index(['type', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check
            CHECK (type IN ('inbound', 'outbound', 'adjustment', 'reservation', 'release'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_check
            CHECK (quantity <> 0 AND (type = 'adjustment' OR quantity > 0))
            SQL);

        // Enforce append-only at the database level, not just in application code.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION stock_movements_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'stock_movements is append-only: % is not allowed', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER stock_movements_append_only
            BEFORE UPDATE OR DELETE ON stock_movements
            FOR EACH ROW EXECUTE FUNCTION stock_movements_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        DB::unprepared('DROP FUNCTION IF EXISTS stock_movements_append_only()');
    }
};
