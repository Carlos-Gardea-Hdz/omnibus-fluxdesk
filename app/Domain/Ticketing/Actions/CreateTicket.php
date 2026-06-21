<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Data\CreateTicketData;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Events\TicketOpened;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\ValueObjects\TicketSubject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Opens a new ticket on behalf of a requester.
 *
 * One class = one business operation. The requester is resolved server-side
 * from the authenticated user — never from client input — to prevent spoofing.
 */
final readonly class CreateTicket
{
    public function handle(CreateTicketData $data, User $requester): Ticket
    {
        // Re-validate the subject through the VO; if it builds, it is valid.
        $subject = new TicketSubject($data->subject);

        // Capture "now" once so created_at and the SLA due_at agree.
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($data, $requester, $subject, $now): Ticket {
            $ticket = Ticket::create([
                'subject' => $subject->value,
                'body' => $data->body,
                'status' => TicketStatus::Open,
                'priority' => $data->priority,
                'requester_id' => $requester->getKey(),
                'assignee_id' => $data->assigneeId,
                'category_id' => $data->categoryId,
                'due_at' => $now->addHours($data->priority->slaHours()),
            ]);

            TicketOpened::dispatch($ticket->id);

            return $ticket;
        });
    }
}
