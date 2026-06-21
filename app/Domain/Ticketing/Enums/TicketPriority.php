<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Enums;

/**
 * Triage priority of a support ticket, ordered by urgency.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Bilingual (ES/EN) human label.
     *
     * @return array{en: string, es: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::Low => ['en' => 'Low', 'es' => 'Baja'],
            self::Medium => ['en' => 'Medium', 'es' => 'Media'],
            self::High => ['en' => 'High', 'es' => 'Alta'],
            self::Urgent => ['en' => 'Urgent', 'es' => 'Urgente'],
        };
    }

    /**
     * Flux badge colour. Pair with the text label — never colour alone.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'zinc',
            self::Medium => 'sky',
            self::High => 'amber',
            self::Urgent => 'red',
        };
    }

    /**
     * Sort weight (higher = more urgent) for ordering queues.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Medium => 1,
            self::High => 2,
            self::Urgent => 3,
        };
    }

    /**
     * Wall-clock hours allotted to resolve a ticket of this priority — the only
     * place the SLA targets live. {@see Ticket::$due_at} is `created_at + slaHours`.
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::Urgent => 4,
            self::High => 24,
            self::Medium => 72,
            self::Low => 168,
        };
    }
}
