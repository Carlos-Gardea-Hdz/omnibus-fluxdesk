<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ticketing\Enums\CategoryColor;
use App\Domain\Ticketing\Models\Category;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed fictional demo data only. NEVER seed real names/emails/PII
     * (project law) — everything here is faker-generated.
     */
    public function run(): void
    {
        $agents = User::factory()->count(3)->create();

        $requesters = User::factory()->count(8)->create();

        // A small fictional taxonomy (no PII), with one archived to exercise the
        // active/archived split.
        $categories = collect([
            ['name' => 'Billing', 'color' => CategoryColor::Emerald],
            ['name' => 'Hardware', 'color' => CategoryColor::Amber],
            ['name' => 'Network', 'color' => CategoryColor::Sky],
            ['name' => 'Access', 'color' => CategoryColor::Violet],
            ['name' => 'Other', 'color' => CategoryColor::Zinc],
        ])->map(fn (array $attributes): Category => Category::factory()->create([
            'name' => $attributes['name'],
            'color' => $attributes['color'],
        ]));

        Category::factory()->archived()->create(['name' => 'Legacy onboarding']);

        // A spread of tickets, some assigned, most categorized, with a varied SLA
        // standing: roughly a third overdue, a third already resolved.
        Ticket::factory()
            ->count(40)
            ->recycle($requesters)
            ->create()
            ->each(function (Ticket $ticket) use ($agents, $categories): void {
                $attributes = [];

                if (fake()->boolean(60)) {
                    $attributes['assignee_id'] = $agents->random()->getKey();
                }

                if (fake()->boolean(80)) {
                    $attributes['category_id'] = $categories->random()->getKey();
                }

                $created = $ticket->created_at !== null
                    ? CarbonImmutable::instance($ticket->created_at)
                    : CarbonImmutable::now();
                $due = $created->addHours($ticket->priority->slaHours());
                $attributes['due_at'] = $due;

                $roll = fake()->numberBetween(1, 3);

                if ($roll === 1) {
                    // Overdue: due in the past, still unresolved.
                    $attributes['created_at'] = CarbonImmutable::now()->subDays(10);
                    $attributes['due_at'] = CarbonImmutable::now()->subDays(2);
                    $attributes['resolved_at'] = null;
                } elseif ($roll === 2) {
                    // Resolved on time.
                    $attributes['resolved_at'] = $due->subHours(1);
                }

                $ticket->update($attributes);
            });
    }
}
