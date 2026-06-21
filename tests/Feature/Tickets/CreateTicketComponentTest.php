<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Events\TicketOpened;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// CREATE-1 — a valid submission persists an Open ticket owned by the agent and
// redirects to its detail page.
it('creates a ticket for the authenticated agent and redirects to it', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->set('subject', 'VPN drops every few minutes')
        ->set('body', 'Since the morning update the VPN disconnects roughly every five minutes.')
        ->set('priority', TicketPriority::High->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $ticket = Ticket::query()->sole();

    expect($ticket->subject)->toBe('VPN drops every few minutes')
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->requester_id)->toBe($agent->getKey())
        ->and($ticket->id)->toBeUuid();
});

// CREATE-2 — invalid input fails validation inline and persists NOTHING (no 500).
it('rejects invalid input and persists no ticket', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->set('subject', 'no')
        ->set('body', 'x')
        ->set('priority', TicketPriority::Low->value)
        ->call('save')
        ->assertHasErrors(['subject', 'body'])
        ->assertNoRedirect();

    $this->assertDatabaseCount('tickets', 0);
});

// CREATE-2b — a whitespace-padded subject that trims to fewer than MIN_LENGTH
// characters fails inline on `subject` and persists nothing. Regression for the
// DTO/VO divergence (B1): the DTO trims before measuring length, so "  x  "
// becomes a validation error rather than reaching the VO throw → no HTTP 500.
it('rejects a whitespace-padded short subject inline without a 500', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->set('subject', '  x  ')
        ->set('body', 'A perfectly valid body that is long enough to pass.')
        ->set('priority', TicketPriority::Low->value)
        ->call('save')
        ->assertHasErrors(['subject'])
        ->assertNoRedirect();

    $this->assertDatabaseCount('tickets', 0);
});

// CREATE-2c — a padded-but-valid subject passes and is persisted trimmed, so the
// DTO and the TicketSubject VO agree on the stored value.
it('persists a padded-but-valid subject trimmed', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->set('subject', '  Hello there  ')
        ->set('body', 'A perfectly valid body that is long enough to pass.')
        ->set('priority', TicketPriority::Medium->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Ticket::query()->sole()->subject)->toBe('Hello there');
});

// CREATE-3 — opening a ticket dispatches the domain event.
it('dispatches the TicketOpened event when a ticket is created', function (): void {
    Event::fake();
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->set('subject', 'Laptop will not boot')
        ->set('body', 'The laptop shows a black screen after the BIOS logo.')
        ->set('priority', TicketPriority::Urgent->value)
        ->call('save')
        ->assertHasNoErrors();

    Event::assertDispatched(TicketOpened::class);
});
