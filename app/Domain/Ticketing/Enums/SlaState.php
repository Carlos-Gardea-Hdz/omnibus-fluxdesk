<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Enums;

/**
 * Read-time SLA standing of a ticket relative to its {@see Ticket::$due_at}.
 *
 * Computed on read (no jobs, no stored column) by {@see \App\Domain\Ticketing\Models\Ticket::slaState()}.
 */
enum SlaState: string
{
    case OnTrack = 'on_track';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';

    /**
     * Bilingual (ES/EN) human label. UI copy stays bilingual (project law).
     *
     * @return array{en: string, es: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::OnTrack => ['en' => 'On track', 'es' => 'En tiempo'],
            self::DueSoon => ['en' => 'Due soon', 'es' => 'Por vencer'],
            self::Overdue => ['en' => 'Overdue', 'es' => 'Vencido'],
        };
    }

    /**
     * Flux badge colour. Never convey the state by colour alone — always pair
     * with the text label (WCAG 1.4.1).
     */
    public function color(): string
    {
        return match ($this) {
            self::OnTrack => 'emerald',
            self::DueSoon => 'amber',
            self::Overdue => 'red',
        };
    }
}
