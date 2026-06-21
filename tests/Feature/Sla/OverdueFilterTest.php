<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\SlaState;
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

// AC-SLA-8c — boundary alignment (W2): at the instant now == due_at the SLA badge
// reads Overdue (model uses >=), so the "overdue only" SQL filter must include the
// row too. Freeze time so the boundary is exact.
it('includes a ticket whose due_at equals now in the overdue filter and badge', function (): void {
    $now = CarbonImmutable::parse('2026-06-21 12:00:00');
    CarbonImmutable::setTestNow($now);

    $agent = User::factory()->create();

    $ticket = Ticket::factory()->create([
        'subject' => 'Boundary marker',
        'status' => TicketStatus::Open,
        'created_at' => $now->subHours(24),
        'due_at' => $now,
        'resolved_at' => null,
        'requester_id' => $agent->getKey(),
    ]);

    // The badge says Overdue at the boundary…
    expect($ticket->slaState($now))->toBe(SlaState::Overdue);

    // …and the filter must agree.
    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('overdueOnly', true)
        ->assertSee('Boundary marker');

    CarbonImmutable::setTestNow();
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
