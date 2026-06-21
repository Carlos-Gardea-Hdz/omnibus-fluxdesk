<?php

declare(strict_types=1);

use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

it('renders the dashboard page with a 200', function (): void {
    // The dashboard moved behind the agent auth gate (slice 001), so a guest is
    // redirected — authenticate to reach it.
    $this->actingAs(User::factory()->create())->get('/')->assertOk();
});

it('counts tickets by status', function (): void {
    Ticket::factory()->count(3)->open()->create();

    Livewire::test('pages::dashboard')
        ->assertOk()
        ->assertSee('3');
});
