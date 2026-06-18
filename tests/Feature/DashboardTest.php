<?php

declare(strict_types=1);

use App\Domain\Ticketing\Models\Ticket;
use Livewire\Livewire;

it('renders the dashboard page with a 200', function (): void {
    $this->get('/')->assertOk();
});

it('counts tickets by status', function (): void {
    Ticket::factory()->count(3)->open()->create();

    Livewire::test('pages::dashboard')
        ->assertOk()
        ->assertSee('3');
});
