<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Exceptions\InvalidTicketTransition;
use App\Domain\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;

/**
 * Moves a ticket to a new lifecycle status, enforcing the legal transition
 * graph. Fails loud on an illegal move (project law).
 *
 * Also manages the SLA clock: moving into a status that stops the clock
 * (Resolved/Closed) stamps resolved_at the first time; moving back into a live
 * status clears it so the SLA recomputes against the live clock.
 */
final readonly class TransitionTicket
{
    public function handle(Ticket $ticket, TicketStatus $target): Ticket
    {
        $current = $ticket->status;

        if (! $current->canTransitionTo($target)) {
            throw InvalidTicketTransition::between($current, $target);
        }

        $attributes = ['status' => $target];

        if ($target->stopsSlaClock()) {
            // Stamp only the first time the clock stops; keep the original
            // resolution timestamp on a Resolved → Closed move.
            if ($ticket->resolved_at === null) {
                $attributes['resolved_at'] = CarbonImmutable::now();
            }
        } else {
            // Re-opened: restart the live SLA clock.
            $attributes['resolved_at'] = null;
        }

        $ticket->update($attributes);

        return $ticket;
    }
}
