<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            // Nullable: a real "uncategorized inbox" + trivial backfill for the
            // slice-001 rows. On category archive/restore we keep the link; on
            // category delete (none happen — archive only) we'd null it out.
            $table->foreignUuid('category_id')
                ->nullable()
                ->after('priority')
                ->constrained('categories')
                ->nullOnDelete();

            // SLA target stamped at creation from the priority (created_at + slaHours).
            $table->timestamp('due_at')->nullable();
            // Stamped when the clock stops (Resolved/Closed); cleared on re-open.
            $table->timestamp('resolved_at')->nullable();

            // Hot paths: live-breach filter and resolved-vs-due SLA reporting.
            $table->index('due_at');
            $table->index(['resolved_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropIndex(['due_at']);
            $table->dropIndex(['resolved_at', 'due_at']);
            $table->dropColumn(['due_at', 'resolved_at']);
        });
    }
};
