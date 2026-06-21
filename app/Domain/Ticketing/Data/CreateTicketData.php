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
}
