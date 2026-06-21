<?php

declare(strict_types=1);

use App\Domain\Ticketing\Actions\CommentOnTicket;
use App\Domain\Ticketing\Data\CommentOnTicketData;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketComment;
use App\Models\User;

covers(CommentOnTicket::class);

it('persists a comment authored by the given user', function (): void {
    $requester = User::factory()->create();
    $author = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $requester->getKey()]);

    $comment = app(CommentOnTicket::class)->handle(
        $ticket,
        new CommentOnTicketData(body: 'Restarting the service now.'),
        $author,
    );

    expect($comment)->toBeInstanceOf(TicketComment::class)
        ->and($comment->body)->toBe('Restarting the service now.')
        ->and($comment->author_id)->toBe($author->getKey())
        ->and($comment->ticket_id)->toBe($ticket->getKey())
        ->and($comment->id)->toBeUuid();

    $this->assertDatabaseHas('ticket_comments', [
        'ticket_id' => $ticket->getKey(),
        'author_id' => $author->getKey(),
        'body' => 'Restarting the service now.',
    ]);
});

it('associates the comment with the ticket relation', function (): void {
    $requester = User::factory()->create();
    $author = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['requester_id' => $requester->getKey()]);

    app(CommentOnTicket::class)->handle(
        $ticket,
        new CommentOnTicketData(body: 'First note on this ticket.'),
        $author,
    );

    expect($ticket->fresh()->comments)->toHaveCount(1);
});
