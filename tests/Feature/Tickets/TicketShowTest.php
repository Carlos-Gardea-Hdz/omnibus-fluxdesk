<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

// XSS — the helpdesk's highest-value invariant: untrusted ticket input renders inert.
it('escapes a stored-XSS payload in the ticket subject (no raw HTML sink)', function (): void {
    $agent = User::factory()->create();
    $payload = '<script>alert(1)</script>';
    $ticket = Ticket::factory()->create([
        'subject' => 'Bug '.$payload,
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->assertOk()
        ->assertDontSee($payload, false) // the RAW <script> must NOT reach the DOM
        ->assertSee($payload);           // the escaped form IS present (rendered as text)
});

// TRANS-1 — a legal transition moves the ticket and reports no error.
it('performs a legal status transition', function (): void {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $agent->getKey()]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->call('changeStatus', TicketStatus::InProgress->value)
        ->assertHasNoErrors();

    expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress);
});

// TRANS-2 — an illegal transition fails gracefully inline, never a 500, and the
// status is untouched.
it('rejects an illegal status transition without a 500', function (): void {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $agent->getKey()]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->call('changeStatus', TicketStatus::Resolved->value)
        ->assertHasErrors('transition');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open);
});

// TRANS-3 — only the allowed transitions are offered (Open → In progress /
// Pending / Closed, never Resolved).
it('only renders the allowed transition controls for the current status', function (): void {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $agent->getKey()]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->assertSee('In progress')
        ->assertSee('Pending')
        ->assertSee('Closed')
        ->assertDontSee('Resolved');
});

// ASSIGN-1 — assigning to an agent reflects in the database.
it('assigns a ticket to an agent', function (): void {
    $agent = User::factory()->create();
    $assignee = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $agent->getKey()]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->set('assigneeId', $assignee->getKey())
        ->call('assign')
        ->assertHasNoErrors();

    expect($ticket->refresh()->assignee_id)->toBe($assignee->getKey());
});

// ASSIGN-2 — clearing the assignee unassigns the ticket.
it('unassigns a ticket when the assignee is cleared', function (): void {
    $agent = User::factory()->create();
    $assignee = User::factory()->create();
    $ticket = Ticket::factory()
        ->open()
        ->assignedTo($assignee)
        ->create(['requester_id' => $agent->getKey()]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->set('assigneeId', null)
        ->call('assign')
        ->assertHasNoErrors();

    expect($ticket->refresh()->assignee_id)->toBeNull();
});

// COMMENT-1 — a valid comment is persisted, authored by the agent, shown, and
// the input is reset.
it('adds a comment authored by the agent', function (): void {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $agent->getKey()]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->set('commentBody', 'Looking into it')
        ->call('addComment')
        ->assertHasNoErrors()
        ->assertSet('commentBody', '')
        ->assertSee('Looking into it');

    $this->assertDatabaseHas('ticket_comments', [
        'ticket_id' => $ticket->getKey(),
        'author_id' => $agent->getKey(),
        'body' => 'Looking into it',
    ]);
});

// COMMENT-2 — a blank comment fails inline and persists nothing.
it('rejects a blank comment', function (): void {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $agent->getKey()]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->set('commentBody', '')
        ->call('addComment')
        ->assertHasErrors('body');

    $this->assertDatabaseCount('ticket_comments', 0);
});
