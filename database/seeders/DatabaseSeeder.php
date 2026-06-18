<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
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

        // A spread of tickets, some assigned to a random agent.
        Ticket::factory()
            ->count(40)
            ->recycle($requesters)
            ->create()
            ->each(function (Ticket $ticket) use ($agents): void {
                if (fake()->boolean(60)) {
                    $ticket->update(['assignee_id' => $agents->random()->getKey()]);
                }
            });
    }
}
