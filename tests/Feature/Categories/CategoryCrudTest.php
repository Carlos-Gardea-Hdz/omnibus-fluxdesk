<?php

declare(strict_types=1);

use App\Domain\Ticketing\Enums\CategoryColor;
use App\Domain\Ticketing\Models\Category;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

// AC-GATE-1 — the categories screen is behind auth; a guest is bounced to login.
it('redirects a guest from the categories screen to login', function (): void {
    $this->get('/categories')->assertRedirect(route('login'));
});

// AC-CAT-1 — a valid submission persists a category.
it('creates a category', function (): void {
    $agent = User::factory()->create();
    $color = CategoryColor::cases()[0];

    Livewire::actingAs($agent)
        ->test('categories::index')
        ->set('name', 'Billing')
        ->set('color', $color->value)
        ->set('description', 'Invoices and payments')
        ->call('save')
        ->assertHasNoErrors();

    $category = Category::query()->sole();

    expect($category->name)->toBe('Billing')
        ->and($category->color)->toBe($color)
        ->and($category->description)->toBe('Invoices and payments')
        ->and($category->isArchived())->toBeFalse()
        ->and($category->id)->toBeUuid();
});

// AC-CAT-2a — a blank name fails inline and persists nothing (no 500).
it('rejects a blank category name inline and persists no row', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('categories::index')
        ->set('name', '')
        ->set('color', CategoryColor::cases()[0]->value)
        ->call('save')
        ->assertHasErrors('name');

    $this->assertDatabaseCount('categories', 0);
});

// AC-CAT-2b — a duplicate name (case-insensitive) fails inline and creates no
// duplicate row. Uniqueness is enforced in the Action (DuplicateCategoryName).
it('rejects a case-insensitive duplicate name inline and creates no duplicate', function (): void {
    $agent = User::factory()->create();
    Category::factory()->create(['name' => 'Network']);

    Livewire::actingAs($agent)
        ->test('categories::index')
        ->set('name', 'NETWORK')
        ->set('color', CategoryColor::cases()[0]->value)
        ->call('save')
        ->assertHasErrors('name');

    expect(Category::query()->count())->toBe(1);
});

// AC-CAT-2c — the DB guard is itself case-INSENSITIVE: even bypassing the Action,
// a name that differs only in case is rejected by the functional unique index on
// lower(name). This closes the exact-case race the case-sensitive unique left open
// (no uncaught 23505 path remains for casing-only collisions).
it('rejects a case-insensitive duplicate at the DB level via the functional index', function (): void {
    Category::factory()->create(['name' => 'Network']);

    // Forced direct insert — no Action pre-check — must still be blocked by the DB.
    // (On PostgreSQL the failed INSERT aborts the surrounding test transaction, so
    // we cannot query afterwards; the thrown 23505 unique-violation is the proof
    // that no casing-only duplicate can ever be persisted.)
    expect(fn (): Category => Category::factory()->create(['name' => 'network']))
        ->toThrow(QueryException::class);
});

// AC-CAT-3 — editing changes the colour of an existing category in place.
it('edits an existing category colour', function (): void {
    $agent = User::factory()->create();
    $original = CategoryColor::cases()[0];
    $next = CategoryColor::cases()[1];
    $category = Category::factory()->create(['name' => 'Hardware', 'color' => $original]);

    Livewire::actingAs($agent)
        ->test('categories::index')
        ->call('edit', $category->getKey())
        ->assertSet('editingId', $category->getKey())
        ->set('color', $next->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($category->refresh()->color)->toBe($next);
    // No new row was created by an edit.
    expect(Category::query()->count())->toBe(1);
});

// AC-CAT-4 — archiving sets archived_at and hides the category from selectors;
// restoring clears it (soft lifecycle, never a hard delete).
it('archives a category then restores it', function (): void {
    $agent = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Access']);

    $component = Livewire::actingAs($agent)->test('categories::index');

    $component->call('archive', $category->getKey())->assertHasNoErrors();
    expect($category->refresh()->archived_at)->not->toBeNull()
        ->and($category->isArchived())->toBeTrue();

    // Active scope excludes the archived category (it is hidden from selectors).
    expect(Category::active()->pluck('id'))->not->toContain($category->getKey());

    $component->call('restore', $category->getKey())->assertHasNoErrors();
    expect($category->refresh()->archived_at)->toBeNull()
        ->and($category->isArchived())->toBeFalse();

    expect(Category::active()->pluck('id'))->toContain($category->getKey());
});

// AC-CAT-5 — a category named with a <script> payload renders inert (escaped)
// on the management list — stored XSS is neutralised by Blade auto-escaping.
it('escapes a stored-XSS payload in the category name on the list', function (): void {
    $agent = User::factory()->create();
    $payload = '<script>alert(1)</script>';
    Category::factory()->create(['name' => 'Bug '.$payload]);

    Livewire::actingAs($agent)
        ->test('categories::index')
        ->assertOk()
        ->assertDontSee($payload, false)        // the RAW <script> must NOT reach the DOM
        ->assertSee('&lt;script&gt;', false);   // the escaped form IS present as text
});
