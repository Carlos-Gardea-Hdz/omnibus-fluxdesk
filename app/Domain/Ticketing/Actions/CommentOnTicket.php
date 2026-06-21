<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Data\CommentOnTicketData;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketComment;
use App\Models\User;

/**
 * Posts a comment on a ticket on behalf of its author.
 *
 * One class = one business operation. The author is resolved server-side from
 * the authenticated user — never from client input — to prevent spoofing.
 */
final readonly class CommentOnTicket
{
    public function handle(Ticket $ticket, CommentOnTicketData $data, User $author): TicketComment
    {
        $comment = new TicketComment([
            'author_id' => $author->getKey(),
            'body' => $data->body,
        ]);

        $comment->ticket()->associate($ticket);
        $comment->save();

        return $comment;
    }
}
