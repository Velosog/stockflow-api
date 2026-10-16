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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // An order is fulfilled from a single warehouse.
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('customer_reference', 64)->nullable();
            $table->bigInteger('total_cents');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('warehouse_id');
            $table->index('created_by');
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'confirmed', 'cancelled'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_cents_check CHECK (total_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
