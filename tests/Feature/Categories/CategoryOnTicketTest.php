<?php

declare(strict_types=1);

use App\Domain\Ticketing\Models\Category;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

// AC-TC-1 — creating a ticket with a category sets category_id and the badge.
it('creates a ticket with a category and shows the category badge', function (): void {
    $agent = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Billing']);

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->set('subject', 'Double charged on my invoice')
        ->set('body', 'My card was charged twice for the same monthly invoice.')
        ->set('priority', 'high')
        ->set('categoryId', $category->getKey())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $ticket = Ticket::query()->sole();

    expect($ticket->category_id)->toBe($category->getKey());

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->assertSee('Billing');
});

// AC-TC-2 — "No category" leaves the ticket uncategorised (null), no error.
it('creates a ticket with no category when none is chosen', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->set('subject', 'General question about the portal')
        ->set('body', 'I cannot find where to update my contact details.')
        ->set('priority', 'low')
        ->set('categoryId', null)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Ticket::query()->sole()->category_id)->toBeNull();
});

// AC-TC-3 — the board category filter narrows the list to matching tickets and
// resets pagination when the filter changes.
it('filters the board by category and resets the page', function (): void {
    $agent = User::factory()->create();
    $billing = Category::factory()->create(['name' => 'Billing']);
    $network = Category::factory()->create(['name' => 'Network']);

    Ticket::factory()->forCategory($billing)->create([
        'subject' => 'Billing marker ticket',
        'requester_id' => $agent->getKey(),
    ]);
    Ticket::factory()->forCategory($network)->create([
        'subject' => 'Network marker ticket',
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->set('category', $billing->getKey())
        ->assertSee('Billing marker ticket')
        ->assertDontSee('Network marker ticket')
        ->assertSet('paginators.page', 1);
});

// AC-TC-4 — an archived category never appears in the create select nor as a
// board filter option (selectors only offer active categories).
it('omits an archived category from the create select and the board filter', function (): void {
    $agent = User::factory()->create();
    $active = Category::factory()->create(['name' => 'ActiveCatMarker']);
    Category::factory()->archived()->create(['name' => 'ArchivedCatMarker']);

    Livewire::actingAs($agent)
        ->test('tickets::create')
        ->assertSee('ActiveCatMarker')
        ->assertDontSee('ArchivedCatMarker');

    Livewire::actingAs($agent)
        ->test('tickets::index')
        ->assertSee('ActiveCatMarker')
        ->assertDontSee('ArchivedCatMarker');
});

// AC-TC-5 — re-categorising a ticket from the detail screen swaps to a valid
// ACTIVE category and persists the change.
it('re-categorises a ticket to an active category and persists it', function (): void {
    $agent = User::factory()->create();
    $billing = Category::factory()->create(['name' => 'Billing']);
    $network = Category::factory()->create(['name' => 'Network']);

    $ticket = Ticket::factory()->forCategory($billing)->create([
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->set('categoryId', $network->getKey())
        ->call('changeCategory')
        ->assertHasNoErrors();

    expect($ticket->refresh()->category_id)->toBe($network->getKey());
});

// AC-TC-6 — re-categorising to an ARCHIVED (or unknown) category id surfaces the
// inline error and does NOT mutate the ticket's category (only active is allowed).
it('rejects re-categorising to an archived category without mutating the ticket', function (): void {
    $agent = User::factory()->create();
    $billing = Category::factory()->create(['name' => 'Billing']);
    $archived = Category::factory()->archived()->create(['name' => 'Archived']);

    $ticket = Ticket::factory()->forCategory($billing)->create([
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->set('categoryId', $archived->getKey())
        ->call('changeCategory')
        ->assertHasErrors('category');

    expect($ticket->refresh()->category_id)->toBe($billing->getKey());
});

// AC-TC-7 — an unknown (non-existent) category id is likewise rejected inline and
// leaves the ticket untouched.
it('rejects re-categorising to an unknown category id without mutating the ticket', function (): void {
    $agent = User::factory()->create();
    $billing = Category::factory()->create(['name' => 'Billing']);

    $ticket = Ticket::factory()->forCategory($billing)->create([
        'requester_id' => $agent->getKey(),
    ]);

    Livewire::actingAs($agent)
        ->test('tickets::show', ['ticket' => $ticket])
        ->set('categoryId', Str::uuid7()->toString())
        ->call('changeCategory')
        ->assertHasErrors('category');

    expect($ticket->refresh()->category_id)->toBe($billing->getKey());
});
