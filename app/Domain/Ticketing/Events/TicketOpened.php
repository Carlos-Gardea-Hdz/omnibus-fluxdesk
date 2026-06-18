<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitted when a ticket is opened. Carries only the id — listeners re-resolve
 * and re-authorize as needed (never trust a serialized model across a queue).
 */
final class TicketOpened
{
    use Dispatchable;

    public function __construct(public string $ticketId) {}
}
