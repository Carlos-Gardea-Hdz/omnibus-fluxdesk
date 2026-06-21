<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

use App\Domain\Ticketing\Enums\CategoryColor;
use Carbon\CarbonImmutable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A ticket category. Lives inside the Ticketing bounded context.
 *
 * NO hard delete — categories are archived (archived_at set) so ticket history
 * is preserved and there is never an orphaned/restricted FK. Active = NULL.
 *
 * @property string $id
 * @property string $name
 * @property CategoryColor $color
 * @property string|null $description
 * @property CarbonImmutable|null $archived_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'color',
        'description',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => CategoryColor::class,
            'archived_at' => 'immutable_datetime',
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
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
