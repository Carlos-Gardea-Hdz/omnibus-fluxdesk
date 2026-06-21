<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Category;

/**
 * Restore an archived category back to active by clearing its archived_at.
 * Idempotent: restoring an active category leaves it active.
 */
final readonly class RestoreCategory
{
    public function handle(Category $category): Category
    {
        $category->update(['archived_at' => null]);

        return $category;
    }
}
