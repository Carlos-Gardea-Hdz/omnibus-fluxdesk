<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
final class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(6),
            'body' => fake()->paragraphs(2, true),
            'status' => fake()->randomElement(TicketStatus::cases()),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'requester_id' => User::factory(),
            'assignee_id' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => TicketStatus::Open]);
    }

    public function assignedTo(User $agent): static
    {
        return $this->state(fn (): array => ['assignee_id' => $agent->getKey()]);
    }
}
