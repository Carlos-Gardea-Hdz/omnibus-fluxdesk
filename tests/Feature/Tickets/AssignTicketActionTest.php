<?php

declare(strict_types=1);

use App\Domain\Ticketing\Actions\AssignTicket;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;

covers(AssignTicket::class);

it('sets the assignee on a ticket', function (): void {
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $requester->getKey()]);

    $result = app(AssignTicket::class)->handle($ticket, $assignee);

    expect($result->assignee_id)->toBe($assignee->getKey())
        ->and($ticket->fresh()->assignee_id)->toBe($assignee->getKey());
});

it('clears the assignee when passed null', function (): void {
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $ticket = Ticket::factory()
        ->open()
        ->assignedTo($assignee)
        ->create(['requester_id' => $requester->getKey()]);

    $result = app(AssignTicket::class)->handle($ticket, null);

    expect($result->assignee_id)->toBeNull()
        ->and($ticket->fresh()->assignee_id)->toBeNull();
});
