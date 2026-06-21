<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Models\Category;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
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
            // Default null keeps slice-001 tests green (uncategorized + no SLA).
            'category_id' => null,
            'due_at' => null,
            'resolved_at' => null,
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

    public function forCategory(Category $category): static
    {
        return $this->state(fn (): array => ['category_id' => $category->getKey()]);
    }

    /**
     * A live-breached ticket: due in the past, still unresolved.
     */
    public function overdue(): static
    {
        return $this->state(function (): array {
            $created = CarbonImmutable::now()->subDays(3);

            return [
                'created_at' => $created,
                'due_at' => $created->addDay(),
                'resolved_at' => null,
            ];
        });
    }

    /**
     * Stamp a resolution timestamp (drives the resolved-vs-due SLA outcome).
     */
    public function resolvedAt(DateTimeInterface $at): static
    {
        return $this->state(fn (): array => [
            'resolved_at' => CarbonImmutable::instance($at),
        ]);
    }
}
