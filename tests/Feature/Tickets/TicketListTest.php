<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Guest gating — every ticket route is behind auth (proven, not just inherited).
it('redirects a guest from the ticket board to login', function (): void {
    $this->get('/tickets')->assertRedirect(route('login'));
});

it('redirects a guest from the create-ticket page to login', function (): void {
    $this->get('/tickets/create')->assertRedirect(route('login'));
});

it('redirects a guest from a ticket detail page to login', function (): void {
    $ticket = Ticket::factory()->create();
    $this->get('/tickets/'.$ticket->id)->assertRedirect(route('login'));
});

// LIST-1 — the status filter narrows the list to matching tickets.
it('filters tickets by status', function (): void {
    $agent = User::factory()->create();

    Ticket::factory()->create([
        'subject' => 'Open subject marker',
        'status' => TicketStatus::Open,
        'requester_id' => $agent->getKey(),
    ]);
    Ticket::factory()->create([
        'subject' => 'Closed subject marker',
        'status' => TicketStatus::Closed,
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('status', TicketStatus::Open->value)
        ->assertSee('Open subject marker')
        ->assertDontSee('Closed subject marker');
});

// LIST-2 — the priority filter narrows the list to matching tickets.
it('filters tickets by priority', function (): void {
    $agent = User::factory()->create();

    Ticket::factory()->create([
        'subject' => 'Urgent subject marker',
        'priority' => TicketPriority::Urgent,
        'requester_id' => $agent->getKey(),
    ]);
    Ticket::factory()->create([
        'subject' => 'Low subject marker',
        'priority' => TicketPriority::Low,
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('priority', TicketPriority::Urgent->value)
        ->assertSee('Urgent subject marker')
        ->assertDontSee('Low subject marker');
});

// LIST-3 — the search box matches a substring of the subject (Postgres ilike).
it('searches tickets by subject substring', function (): void {
    $agent = User::factory()->create();

    Ticket::factory()->create([
        'subject' => 'Printer jam on floor three',
        'requester_id' => $agent->getKey(),
    ]);
    Ticket::factory()->create([
        'subject' => 'Email outage in finance',
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('search', 'printer')
        ->assertSee('Printer jam on floor three')
        ->assertDontSee('Email outage in finance');
});

// LIST-4 — pagination caps a page at 15 rows and the requester/assignee joins
// do not regress into an N+1 (eager loaded).
it('paginates the board at fifteen rows without an N+1', function (): void {
    $agent = User::factory()->create();
    Ticket::factory()->count(20)->create(['requester_id' => $agent->getKey()]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->assertViewHas('tickets', fn ($tickets): bool => $tickets->count() === 15);

    // Eager loading keeps the page render to a small, bounded number of queries
    // (page + count + requester batch + assignee batch), never one-per-row.
    expect($queries)->toBeLessThan(15);
});
