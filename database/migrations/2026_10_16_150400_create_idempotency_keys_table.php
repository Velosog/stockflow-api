<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers the response to a POST sent with an Idempotency-Key header so a
 * retried request returns the original response instead of acting twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            // SHA-256 of method + path + canonical JSON body.
            $table->char('request_fingerprint', 64);
            // NULL while the first request is still being processed.
            $table->smallInteger('response_status')->nullable();
            // Raw body (text, not jsonb) so a replay is byte-for-byte identical.
            $table->text('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            // Keys are scoped per user: two clients may pick the same key.
            $table->unique(['user_id', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
