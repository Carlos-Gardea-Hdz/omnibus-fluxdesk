<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use App\Domain\Ticketing\Enums\CategoryColor;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

/**
 * Input contract for creating and updating a category. Spatie Data is the single
 * source of truth for validation (project law).
 *
 * Name uniqueness is NOT a DTO rule: it is case-insensitive + ignore-self, which
 * a static Unique attribute cannot express without rejecting a row's own name on
 * update. It is enforced in the Actions (throwing DuplicateCategoryName), so one
 * route-agnostic DTO serves both create and update.
 */
final class CategoryData extends Data
{
    public function __construct(
        #[StringType, Min(2), Max(60)]
        public string $name,
        public CategoryColor $color,
        #[Nullable, StringType, Max(280)]
        public ?string $description = null,
    ) {}
}
