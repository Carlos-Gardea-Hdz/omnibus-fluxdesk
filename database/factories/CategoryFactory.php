<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ticketing\Enums\CategoryColor;
use App\Domain\Ticketing\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Fictional data only — NO real PII (project law).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $words */
        $words = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($words),
            'color' => fake()->randomElement(CategoryColor::cases()),
            'description' => fake()->boolean(70) ? fake()->sentence(8) : null,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}
