<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Domain\Ticketing\Enums\SlaState;
use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
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
 * @property string|null $category_id
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $resolved_at
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
        'category_id',
        'due_at',
        'resolved_at',
    ];

    /**
     * Hours before {@see $due_at} at which a live ticket flips to "due soon".
     */
    public const int DUE_SOON_THRESHOLD_HOURS = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'due_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
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

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * The SLA standing of this ticket, computed on read (no query, no job).
     *
     * - No due date set            → null (renders "—").
     * - Already resolved/closed    → met if resolved on/before due, else Overdue.
     * - Live                       → Overdue once past due, DueSoon within the
     *   threshold window, otherwise OnTrack.
     */
    public function slaState(?CarbonImmutable $now = null): ?SlaState
    {
        $due = $this->due_at;

        if ($due === null) {
            return null;
        }

        if ($this->resolved_at !== null) {
            return $this->resolved_at->lessThanOrEqualTo($due)
                ? SlaState::OnTrack
                : SlaState::Overdue;
        }

        $now ??= CarbonImmutable::now();

        if ($now->greaterThanOrEqualTo($due)) {
            return SlaState::Overdue;
        }

        if ($now->greaterThanOrEqualTo($due->subHours(self::DUE_SOON_THRESHOLD_HOURS))) {
            return SlaState::DueSoon;
        }

        return SlaState::OnTrack;
    }

    /**
     * Whether the ticket is past due and still unresolved right now — the live
     * breach predicate used by the "overdue only" board filter (mirrored in SQL).
     */
    public function isSlaBreachedLive(?CarbonImmutable $now = null): bool
    {
        if ($this->due_at === null || $this->resolved_at !== null) {
            return false;
        }

        $now ??= CarbonImmutable::now();

        return $now->greaterThanOrEqualTo($this->due_at);
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }
}
