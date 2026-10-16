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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            // Stored as text + CHECK instead of a native enum type: adding a
            // role later is a one-line constraint change, not an ALTER TYPE.
            $table->string('role', 16)->index();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'manager', 'operator'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
