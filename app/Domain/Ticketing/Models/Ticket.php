<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Models\User;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A support ticket.
 *
 * @property string $id
 * @property string $subject
 * @property string $body
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property string $requester_id
 * @property string|null $assignee_id
 */
final class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'subject',
        'body',
        'status',
        'priority',
        'requester_id',
        'assignee_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
        ];
    }

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
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticket_id');
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }
}
