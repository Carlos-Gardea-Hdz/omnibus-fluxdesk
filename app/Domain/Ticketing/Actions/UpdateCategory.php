<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Data\CategoryData;
use App\Domain\Ticketing\Exceptions\DuplicateCategoryName;
use App\Domain\Ticketing\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Update a category's name, colour and description. One business operation.
 *
 * The case-insensitive uniqueness check excludes the row being edited so saving
 * a category without renaming it never trips the duplicate guard.
 */
final readonly class UpdateCategory
{
    public function handle(Category $category, CategoryData $data): Category
    {
        $this->assertNameAvailable($data->name, $category);

        return DB::transaction(function () use ($category, $data): Category {
            $category->update([
                'name' => $data->name,
                'color' => $data->color,
                'description' => $data->description,
            ]);

            return $category;
        });
    }

    private function assertNameAvailable(string $name, Category $current): void
    {
        $exists = Category::query()
            ->whereKeyNot($current->getKey())
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw DuplicateCategoryName::for($name);
        }
    }
}
