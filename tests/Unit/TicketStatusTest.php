<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\TicketStatus;

covers(TicketStatus::class);

it('allows a legal forward transition', function (): void {
    expect(TicketStatus::Open->canTransitionTo(TicketStatus::InProgress))->toBeTrue();
});

it('rejects an illegal transition', function (): void {
    expect(TicketStatus::Closed->canTransitionTo(TicketStatus::Resolved))->toBeFalse();
});

it('treats closed as terminal for normal flow', function (): void {
    expect(TicketStatus::Closed->isTerminal())->toBeTrue()
        ->and(TicketStatus::Open->isTerminal())->toBeFalse();
});

it('exposes a bilingual label for every case', function (TicketStatus $status): void {
    expect($status->label())->toHaveKeys(['en', 'es'])
        ->and($status->label()['en'])->not->toBeEmpty()
        ->and($status->label()['es'])->not->toBeEmpty();
})->with(TicketStatus::cases());

it('exposes a colour for every case', function (TicketStatus $status): void {
    expect($status->color())->toBeString()->not->toBeEmpty();
})->with(TicketStatus::cases());
