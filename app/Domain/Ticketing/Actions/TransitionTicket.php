<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Exceptions\InvalidTicketTransition;
use App\Domain\Ticketing\Models\Ticket;

/**
 * Moves a ticket to a new lifecycle status, enforcing the legal transition
 * graph. Fails loud on an illegal move (project law).
 */
final readonly class TransitionTicket
{
    public function handle(Ticket $ticket, TicketStatus $target): Ticket
    {
        $current = $ticket->status;

        if (! $current->canTransitionTo($target)) {
            throw InvalidTicketTransition::between($current, $target);
        }

        $ticket->update(['status' => $target]);

        return $ticket;
    }
}
