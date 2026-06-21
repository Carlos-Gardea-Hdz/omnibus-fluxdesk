<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\TicketPriority;

// The SLA-hour budget lives in exactly one place — the priority enum. This is
// the single tuning seam; if these numbers drift, every due_at computation does.
it('maps each priority to its SLA hour budget', function (): void {
    expect(TicketPriority::Urgent->slaHours())->toBe(4)
        ->and(TicketPriority::High->slaHours())->toBe(24)
        ->and(TicketPriority::Medium->slaHours())->toBe(72)
        ->and(TicketPriority::Low->slaHours())->toBe(168);
});

it('orders the SLA budget by urgency (more urgent = fewer hours)', function (): void {
    expect(TicketPriority::Urgent->slaHours())->toBeLessThan(TicketPriority::High->slaHours())
        ->and(TicketPriority::High->slaHours())->toBeLessThan(TicketPriority::Medium->slaHours())
        ->and(TicketPriority::Medium->slaHours())->toBeLessThan(TicketPriority::Low->slaHours());
});
