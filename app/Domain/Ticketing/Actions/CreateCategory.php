<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Data\CategoryData;
use App\Domain\Ticketing\Exceptions\DuplicateCategoryName;
use App\Domain\Ticketing\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Create a category. One business operation.
 *
 * Name uniqueness is case-insensitive and enforced here (not via a DTO rule) so
 * the same route-agnostic DTO serves create and update; a functional unique
 * index on lower(name) is the final, case-insensitive DB guard.
 */
final readonly class CreateCategory
{
    public function handle(CategoryData $data): Category
    {
        $this->assertNameAvailable($data->name);

        return DB::transaction(fn (): Category => Category::create([
            'name' => $data->name,
            'color' => $data->color,
            'description' => $data->description,
        ]));
    }

    private function assertNameAvailable(string $name): void
    {
        // Both sides go through Postgres lower() so the app pre-check uses the
        // exact same collation as the functional unique index on lower(name) —
        // mb_strtolower can diverge for some non-ASCII, which would let a name
        // slip past here only to trip a raw 23505 (uncaught → 500).
        $exists = Category::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->exists();

        if ($exists) {
            throw DuplicateCategoryName::for($name);
        }
    }
}
