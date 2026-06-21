<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

/**
 * Input contract for posting a comment on a ticket. Validation rules live here
 * as the single source of truth (project law: Spatie Data for the typed path).
 * The author is resolved server-side from the authenticated user — never input.
 */
final class CommentOnTicketData extends Data
{
    public function __construct(
        #[StringType, Min(1), Max(5000)]
        public string $body,
    ) {}
}
