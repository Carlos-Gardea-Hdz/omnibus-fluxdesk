<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Exceptions;

use App\Domain\Ticketing\Enums\TicketStatus;
use DomainException;

/**
 * Thrown when a ticket is asked to move to a status its lifecycle graph forbids.
 * Fails loud (project law) — never silently no-op an illegal transition.
 */
final class InvalidTicketTransition extends DomainException
{
    public static function between(TicketStatus $from, TicketStatus $to): self
    {
        return new self(
            "Cannot transition ticket from [{$from->value}] to [{$to->value}]."
        );
    }
}
