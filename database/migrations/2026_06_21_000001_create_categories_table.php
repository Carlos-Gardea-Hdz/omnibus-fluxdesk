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
        Schema::create('categories', function (Blueprint $table): void {
            // UUIDv7 primary key (chronologically sortable, opaque in URLs).
            $table->uuid('id')->primary();

            $table->string('name', 60);
            // Constrained to a CategoryColor enum token at the application layer.
            $table->string('color', 20);
            $table->string('description', 280)->nullable();

            // Archive instead of hard-delete: preserves ticket history with no
            // orphan/restrict-FK problem. Active = NULL.
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            $table->index('archived_at');
        });

        // Case-INSENSITIVE DB uniqueness as the final guard — a functional unique
        // index on lower(name) mirrors the Action's case-insensitive pre-check, so
        // an exact-case concurrent duplicate is rejected by the DB (no uncaught
        // 23505) exactly where the app would have rejected it.
        DB::statement('CREATE UNIQUE INDEX categories_name_lower_unique ON categories (lower(name))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS categories_name_lower_unique');
        Schema::dropIfExists('categories');
    }
};
