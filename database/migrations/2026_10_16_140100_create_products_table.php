<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Unique across soft-deleted rows too: a SKU is never reused.
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // Money as integer cents: no floating point rounding errors.
            $table->integer('price_cents');
            // Alert when available stock in a warehouse drops below this value.
            $table->integer('low_stock_threshold')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_cents_check CHECK (price_cents >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_low_stock_threshold_check CHECK (low_stock_threshold >= 0)');

        // Trigram index so the "search" filter (ILIKE '%term%') can use an
        // index instead of a sequential scan. pg_trgm is a trusted extension.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX products_name_trgm_index ON products USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
