<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Models\User;
use Database\Factories\TicketCommentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A comment posted on a ticket by an agent (the author).
 *
 * @property string $id
 * @property string $ticket_id
 * @property string $author_id
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Ticket $ticket
 * @property-read User $author
 */
final class TicketComment extends Model
{
    /** @use HasFactory<TicketCommentFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'ticket_id',
        'author_id',
        'body',
    ];

    /**
     * UUIDs are minted as UUIDv7 (chronologically sortable) per project law.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function newFactory(): TicketCommentFactory
    {
        return TicketCommentFactory::new();
    }
}
