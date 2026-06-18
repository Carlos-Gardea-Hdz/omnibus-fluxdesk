<?php

declare(strict_types=1);

use App\Domain\Ticketing\ValueObjects\TicketSubject;

covers(TicketSubject::class);

it('accepts and trims a valid subject', function (): void {
    expect((new TicketSubject('  Printer is jammed  '))->value)
        ->toBe('Printer is jammed');
});

it('rejects a subject that is too short', function (): void {
    new TicketSubject('hi');
})->throws(InvalidArgumentException::class);

it('rejects a subject that exceeds the maximum length', function (): void {
    new TicketSubject(str_repeat('a', TicketSubject::MAX_LENGTH + 1));
})->throws(InvalidArgumentException::class);
