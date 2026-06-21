<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Category;
use Carbon\CarbonImmutable;

/**
 * Archive a category (soft retire). No hard delete — ticket history is preserved
 * and the FK never orphans. Idempotent: re-archiving leaves it archived.
 */
final readonly class ArchiveCategory
{
    public function handle(Category $category): Category
    {
        $category->update(['archived_at' => CarbonImmutable::now()]);

        return $category;
    }
}
