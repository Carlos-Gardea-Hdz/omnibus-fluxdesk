<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_comments', function (Blueprint $table): void {
            // UUIDv7 primary key (chronologically sortable, opaque in URLs).
            $table->uuid('id')->primary();

            $table->foreignUuid('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            $table->foreignUuid('author_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('body');

            $table->timestamps();

            // Hot path: load a ticket's comment thread in chronological order.
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
    }
};
