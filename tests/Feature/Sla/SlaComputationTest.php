<?php

declare(strict_types=1);

use App\Domain\Ticketing\Actions\TransitionTicket;
use App\Domain\Ticketing\Enums\SlaState;
use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

// AC-SLA-1 — a fresh Urgent ticket's due_at lands ~4h after it was created.
it('stamps due_at at created_at plus the priority SLA budget', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->set('subject', 'Production is down for everyone')
        ->set('body', 'The whole production site returns 500 for all users.')
        ->set('priority', TicketPriority::Urgent->value)
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::query()->sole();

    expect($ticket->due_at)->not->toBeNull();

    $expected = CarbonImmutable::parse($ticket->created_at)
        ->addHours(TicketPriority::Urgent->slaHours());

    // due_at = created_at + slaHours(), within a 1s tolerance.
    expect(abs(CarbonImmutable::parse($ticket->due_at)->diffInSeconds($expected)))
        ->toBeLessThanOrEqual(1);
});

// AC-SLA-2 — a freshly created Medium ticket is comfortably on track.
it('reports a fresh ticket as on track', function (): void {
    $ticket = Ticket::factory()->create([
        'priority' => TicketPriority::Medium,
        'created_at' => CarbonImmutable::now(),
        'due_at' => CarbonImmutable::now()->addHours(TicketPriority::Medium->slaHours()),
        'resolved_at' => null,
    ]);

    expect($ticket->slaState())->toBe(SlaState::OnTrack)
        ->and($ticket->isSlaBreachedLive())->toBeFalse();
});

// AC-SLA-3 — a High ticket created 48h ago and still unresolved is overdue, the
// board renders the Overdue badge, and the overdue filter includes it.
it('reports an old unresolved ticket as overdue and surfaces it on the board', function (): void {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'subject' => 'Stale high ticket marker',
        'priority' => TicketPriority::High,
        'status' => TicketStatus::Open,
        'created_at' => CarbonImmutable::now()->subHours(48),
        'due_at' => CarbonImmutable::now()->subHours(24), // High = 24h budget
        'resolved_at' => null,
        'requester_id' => $agent->getKey(),
    ]);

    expect($ticket->slaState())->toBe(SlaState::Overdue)
        ->and($ticket->isSlaBreachedLive())->toBeTrue();

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->assertSee('Stale high ticket marker')
        ->set('overdueOnly', true)
        ->assertSee('Stale high ticket marker');
});

// AC-SLA-4 — a ticket due within the due-soon threshold reads due_soon.
it('reports a ticket near its deadline as due soon', function (): void {
    $ticket = Ticket::factory()->create([
        'priority' => TicketPriority::Medium,
        'created_at' => CarbonImmutable::now()->subHours(68),
        'due_at' => CarbonImmutable::now()->addHours(4), // within the 8h due-soon window
        'resolved_at' => null,
    ]);

    expect($ticket->slaState())->toBe(SlaState::DueSoon)
        ->and($ticket->isSlaBreachedLive())->toBeFalse();
});

// AC-SLA-5 — a ticket resolved before its deadline is met (on track), is not
// overdue, and never appears in the live overdue filter.
it('reports a ticket resolved before its deadline as met and not overdue', function (): void {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'subject' => 'Resolved in time marker',
        'priority' => TicketPriority::Urgent,
        'status' => TicketStatus::Resolved,
        'created_at' => CarbonImmutable::now()->subHours(5),
        'due_at' => CarbonImmutable::now()->subHours(1),
        'resolved_at' => CarbonImmutable::now()->subHours(2), // resolved BEFORE due_at
        'requester_id' => $agent->getKey(),
    ]);

    expect($ticket->slaState())->toBe(SlaState::OnTrack)
        ->and($ticket->isSlaBreachedLive())->toBeFalse();

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('overdueOnly', true)
        ->assertDontSee('Resolved in time marker');
});

// AC-SLA-6 — a ticket resolved AFTER its deadline records an Overdue historical
// state, yet is NOT a live breach (the clock has stopped).
it('keeps an overdue historical state but excludes a resolved ticket from the live filter', function (): void {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'subject' => 'Late resolved marker',
        'priority' => TicketPriority::High,
        'status' => TicketStatus::Resolved,
        'created_at' => CarbonImmutable::now()->subHours(48),
        'due_at' => CarbonImmutable::now()->subHours(24),
        'resolved_at' => CarbonImmutable::now()->subHours(1), // resolved AFTER due_at
        'requester_id' => $agent->getKey(),
    ]);

    expect($ticket->slaState())->toBe(SlaState::Overdue)
        ->and($ticket->isSlaBreachedLive())->toBeFalse();

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('overdueOnly', true)
        ->assertDontSee('Late resolved marker');
});

// AC-SLA-7 — moving into Resolved stamps resolved_at; re-opening clears it.
it('stamps resolved_at on resolution and clears it on re-open', function (): void {
    $action = app(TransitionTicket::class);

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::InProgress,
        'priority' => TicketPriority::Medium,
        'due_at' => CarbonImmutable::now()->addHours(72),
        'resolved_at' => null,
    ]);

    $action->handle($ticket, TicketStatus::Resolved);
    expect($ticket->refresh()->resolved_at)->not->toBeNull();

    $action->handle($ticket, TicketStatus::Open);
    expect($ticket->refresh()->resolved_at)->toBeNull();
});
