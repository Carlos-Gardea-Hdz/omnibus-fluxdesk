<?php

declare(strict_types=1);

// Guards against translation-key drift: every key the slice-002 UI renders must
// resolve to real copy in BOTH locales. A missing key makes Laravel echo the key
// verbatim (e.g. "categories.title"), so the UI shows raw keys — this catches that.

// The authoritative key set, derived from the Blade views' __() calls.
$categoryKeys = [
    'title', 'subtitle', 'new', 'name', 'color', 'description', 'manage',
    'edit', 'archive', 'restore', 'save', 'cancel', 'show_archived', 'empty',
    'name_taken',
];

// The tickets.* keys introduced by this slice (categories + SLA on the board).
$ticketKeys = [
    'category', 'all_categories', 'no_category', 'sla', 'due_at', 'overdue_only',
];

dataset('locales', ['en', 'es']);

it('resolves every categories.* key in both locales', function (string $locale) use ($categoryKeys): void {
    app()->setLocale($locale);

    foreach ($categoryKeys as $key) {
        $full = "categories.{$key}";
        expect(__($full))
            ->not->toBe($full, "categories.{$key} is missing in [{$locale}]");
    }
})->with('locales');

it('resolves the new tickets.* keys in both locales', function (string $locale) use ($ticketKeys): void {
    app()->setLocale($locale);

    foreach ($ticketKeys as $key) {
        $full = "tickets.{$key}";
        expect(__($full))
            ->not->toBe($full, "tickets.{$key} is missing in [{$locale}]");
    }
})->with('locales');

it('keeps categories.php key sets identical across en and es', function (): void {
    $en = array_keys(require base_path('lang/en/categories.php'));
    $es = array_keys(require base_path('lang/es/categories.php'));

    sort($en);
    sort($es);

    expect($es)->toBe($en);
});

it('keeps tickets.php key sets identical across en and es', function (): void {
    $en = array_keys(require base_path('lang/en/tickets.php'));
    $es = array_keys(require base_path('lang/es/tickets.php'));

    sort($en);
    sort($es);

    expect($es)->toBe($en);
});
