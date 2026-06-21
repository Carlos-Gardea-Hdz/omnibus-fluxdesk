<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\CategoryColor;

// CategoryColor is an allow-list of Flux palette tokens. By constraining the
// `color=` prop to this enum, a category colour can never inject markup.
it('exposes a non-empty allow-list of palette tokens', function (): void {
    expect(CategoryColor::cases())->not->toBeEmpty();
});

it('returns its backing value as the Flux token', function (): void {
    foreach (CategoryColor::cases() as $color) {
        expect($color->token())->toBe($color->value)
            ->and($color->token())->toMatch('/^[a-z]+$/'); // safe token, no markup
    }
});

it('exposes a human display string for the picker', function (): void {
    foreach (CategoryColor::cases() as $color) {
        expect($color->display())->toBeString()->not->toBe('');
    }
});
