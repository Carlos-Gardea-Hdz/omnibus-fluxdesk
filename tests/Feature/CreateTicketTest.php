<?php

declare(strict_types=1);

use App\Domain\Ticketing\Actions\CreateTicket;
use App\Domain\Ticketing\Data\CreateTicketData;
use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Events\TicketOpened;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

covers(CreateTicket::class);

it('opens a ticket for the authenticated requester', function (): void {
    Event::fake();
    $requester = User::factory()->create();

    $ticket = app(CreateTicket::class)->handle(
        new CreateTicketData(
            subject: 'Printer is jammed on floor 3',
            body: 'The colour printer keeps eating paper since this morning.',
            priority: TicketPriority::High,
        ),
        $requester,
    );

    expect($ticket)->toBeInstanceOf(Ticket::class)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->requester_id)->toBe($requester->getKey())
        ->and($ticket->id)->toBeUuid();

    Event::assertDispatched(TicketOpened::class);
});

it('validates the input contract through the DTO', function (): void {
    CreateTicketData::validate([
        'subject' => 'no',
        'body' => 'x',
        'priority' => TicketPriority::Low->value,
    ]);
})->throws(ValidationException::class);
