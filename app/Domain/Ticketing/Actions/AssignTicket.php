<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;

/**
 * Assigns a ticket to an agent, or unassigns it when given null.
 *
 * One class = one business operation. The assignee is resolved to a concrete
 * User upstream; passing null clears the assignment.
 */
final readonly class AssignTicket
{
    public function handle(Ticket $ticket, ?User $assignee): Ticket
    {
        $ticket->update([
            'assignee_id' => $assignee?->getKey(),
        ]);

        return $ticket;
    }
}
