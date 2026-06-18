<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            // UUIDv7 primary key (chronologically sortable, opaque in URLs).
            $table->uuid('id')->primary();

            $table->string('subject', 160);
            $table->text('body');

            $table->string('status')->default(TicketStatus::Open->value);
            $table->string('priority')->default(TicketPriority::Medium->value);

            $table->foreignUuid('requester_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('assignee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Hot-path indexes: open queues filtered by status/priority and
            // "my assigned tickets" lookups.
            $table->index(['status', 'priority']);
            $table->index('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
