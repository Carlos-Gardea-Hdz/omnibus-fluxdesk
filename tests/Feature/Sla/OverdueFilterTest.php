<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// AC-SLA-8 — the "overdue only" filter returns exactly the live-breach rows and
// does so in SQL (a bounded query count, never one-per-row PHP evaluation).
it('returns only live-breach rows via SQL when the overdue filter is on', function (): void {
    $agent = User::factory()->create();

    // Live breach: past due_at, unresolved.
    Ticket::factory()->overdue()->create([
        'subject' => 'Live breach marker',
        'requester_id' => $agent->getKey(),
    ]);

    // Not a breach: comfortably in the future.
    Ticket::factory()->create([
        'subject' => 'Healthy marker',
        'status' => TicketStatus::Open,
        'created_at' => CarbonImmutable::now(),
        'due_at' => CarbonImmutable::now()->addHours(72),
        'resolved_at' => null,
        'requester_id' => $agent->getKey(),
    ]);

    // Not a live breach: past due_at but already resolved (clock stopped).
    Ticket::factory()->create([
        'subject' => 'Past but resolved marker',
        'status' => TicketStatus::Resolved,
        'created_at' => CarbonImmutable::now()->subHours(48),
        'due_at' => CarbonImmutable::now()->subHours(24),
        'resolved_at' => CarbonImmutable::now()->subHour(),
        'requester_id' => $agent->getKey(),
    ]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('overdueOnly', true)
        ->assertSee('Live breach marker')
        ->assertDontSee('Healthy marker')
        ->assertDontSee('Past but resolved marker');

    // The filter is pushed into SQL — the render stays bounded, never N+1.
    expect($queries)->toBeLessThan(15);
});

// AC-SLA-8b — pagination still caps a page at 15 rows with the overdue filter on.
it('still paginates at fifteen rows with the overdue filter on', function (): void {
    $agent = User::factory()->create();

    Ticket::factory()->count(20)->overdue()->create([
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('overdueOnly', true)
        ->assertViewHas('tickets', fn ($tickets): bool => $tickets->count() === 15);
});
