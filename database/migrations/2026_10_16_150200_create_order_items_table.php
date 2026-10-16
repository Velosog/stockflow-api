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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            // Price snapshot: later catalog price changes do not alter the order.
            $table->integer('unit_price_cents');
            $table->bigInteger('line_total_cents');
            $table->timestamps();

            $table->unique(['order_id', 'product_id']);
            $table->index('product_id');
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_check CHECK (unit_price_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
