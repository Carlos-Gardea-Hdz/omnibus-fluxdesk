<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\ValueObjects\TicketSubject;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

/**
 * Input contract for opening a ticket. Validation rules live here as the single
 * source of truth (project law: Spatie Data for the typed path). Server-side
 * validation runs before the Action — never trust the client.
 */
final class CreateTicketData extends Data
{
    public function __construct(
        #[StringType, Min(TicketSubject::MIN_LENGTH), Max(TicketSubject::MAX_LENGTH)]
        public string $subject,
        #[StringType, Min(3), Max(5000)]
        public string $body,
        public TicketPriority $priority = TicketPriority::Medium,
        public ?string $assigneeId = null,
        #[Nullable, Uuid, Exists('categories', 'id')]
        public ?string $categoryId = null,
    ) {}

    /**
     * Normalise the subject BEFORE validation runs so the DTO and the
     * {@see TicketSubject} VO measure the same string. The VO trims then guards
     * length; if the DTO validated the untrimmed input, a whitespace-padded but
     * short subject (e.g. "  x  ") would pass Min(3) here yet throw inside the VO
     * — surfacing as an HTTP 500 instead of an inline field error. Trimming up
     * front keeps the DTO the single source of truth: a trim-to-short subject
     * becomes a `subject` validation error and never reaches the VO throw.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (isset($properties['subject']) && is_string($properties['subject'])) {
            $properties['subject'] = trim($properties['subject']);
        }

        return $properties;
    }
}
