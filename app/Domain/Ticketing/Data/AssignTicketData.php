<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

/**
 * Input contract for (re)assigning a ticket to an agent. A null assignee
 * unassigns the ticket. Validation rules live here as the single source of
 * truth (project law: Spatie Data for the typed path).
 */
final class AssignTicketData extends Data
{
    public function __construct(
        #[Nullable, Uuid, Exists('users', 'id')]
        public ?string $assigneeId = null,
    ) {}
}
