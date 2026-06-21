<?php

declare(strict_types=1);

use App\Domain\Ticketing\Actions\TransitionTicket;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Exceptions\InvalidTicketTransition;
use App\Domain\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->action = app(TransitionTicket::class);
});

// Moving into a clock-stopping status (Resolved) stamps resolved_at.
it('stamps resolved_at when moving into Resolved', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::InProgress,
        'due_at' => CarbonImmutable::now()->addHours(24),
        'resolved_at' => null,
    ]);

    $this->action->handle($ticket, TicketStatus::Resolved);

    expect($ticket->refresh()->resolved_at)->not->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Resolved);
});

// Closed also stops the clock (stopsSlaClock = Resolved || Closed).
it('stamps resolved_at when moving into Closed', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'due_at' => CarbonImmutable::now()->addHours(24),
        'resolved_at' => null,
    ]);

    $this->action->handle($ticket, TicketStatus::Closed);

    expect($ticket->refresh()->resolved_at)->not->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Closed);
});

// Moving back to a live status (Open) clears resolved_at — the clock resumes.
it('clears resolved_at when re-opening a resolved ticket', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Resolved,
        'due_at' => CarbonImmutable::now()->addHours(24),
        'resolved_at' => CarbonImmutable::now()->subHour(),
    ]);

    $this->action->handle($ticket, TicketStatus::Open);

    expect($ticket->refresh()->resolved_at)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Open);
});

// The SLA clock logic does not weaken the transition guard: illegal moves still
// throw and leave both status and resolved_at untouched.
it('still rejects an illegal transition and leaves the clock untouched', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'due_at' => CarbonImmutable::now()->addHours(24),
        'resolved_at' => null,
    ]);

    expect(fn (): Ticket => $this->action->handle($ticket, TicketStatus::Resolved))
        ->toThrow(InvalidTicketTransition::class);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->resolved_at)->toBeNull();
});
