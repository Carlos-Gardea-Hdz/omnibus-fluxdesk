<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\SlaState;

// SlaState is a pure presentation enum: bilingual labels + a WCAG-safe colour
// paired with that text label (never colour alone).
it('is a string-backed enum with the three SLA states', function (): void {
    expect(SlaState::OnTrack->value)->toBe('on_track')
        ->and(SlaState::DueSoon->value)->toBe('due_soon')
        ->and(SlaState::Overdue->value)->toBe('overdue');
});

it('exposes a bilingual label for every state', function (): void {
    foreach (SlaState::cases() as $state) {
        $label = $state->label();

        expect($label)->toHaveKeys(['en', 'es'])
            ->and($label['en'])->toBeString()->not->toBe('')
            ->and($label['es'])->toBeString()->not->toBe('');
    }
});

it('uses the agreed bilingual copy', function (): void {
    expect(SlaState::OnTrack->label())->toBe(['en' => 'On track', 'es' => 'En tiempo'])
        ->and(SlaState::DueSoon->label())->toBe(['en' => 'Due soon', 'es' => 'Por vencer'])
        ->and(SlaState::Overdue->label())->toBe(['en' => 'Overdue', 'es' => 'Vencido']);
});

it('maps each state to a distinct WCAG-safe colour', function (): void {
    expect(SlaState::OnTrack->color())->toBe('emerald')
        ->and(SlaState::DueSoon->color())->toBe('amber')
        ->and(SlaState::Overdue->color())->toBe('red');
});
