<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Enums;

/**
 * Lifecycle states of a support ticket.
 *
 * Transitions are enforced via {@see self::canTransitionTo()} — never mutate
 * status without checking the allowed graph first.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Bilingual (ES/EN) human label. UI copy stays bilingual (project law);
     * the frontend picks the key via the language hook.
     *
     * @return array{en: string, es: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::Open => ['en' => 'Open', 'es' => 'Abierto'],
            self::InProgress => ['en' => 'In progress', 'es' => 'En progreso'],
            self::Pending => ['en' => 'Pending', 'es' => 'Pendiente'],
            self::Resolved => ['en' => 'Resolved', 'es' => 'Resuelto'],
            self::Closed => ['en' => 'Closed', 'es' => 'Cerrado'],
        };
    }

    /**
     * Flux badge colour. Never convey state by colour alone — always pair with
     * the text label (WCAG 1.4.1).
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'sky',
            self::InProgress => 'amber',
            self::Pending => 'zinc',
            self::Resolved => 'emerald',
            self::Closed => 'zinc',
        };
    }

    /**
     * Whether this ticket may move to the given status. Encodes the only legal
     * lifecycle graph; callers MUST consult this before persisting a change.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Pending, self::Closed],
            self::InProgress => [self::Pending, self::Resolved, self::Closed],
            self::Pending => [self::InProgress, self::Resolved, self::Closed],
            self::Resolved => [self::Closed, self::Open],
            self::Closed => [self::Open],
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }
}
