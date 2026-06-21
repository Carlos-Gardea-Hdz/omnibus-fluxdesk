<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

// AUTH-1 — the ticket board is gated: a guest is bounced to the login screen.
it('redirects a guest away from the ticket board to login', function (): void {
    $this->get('/tickets')->assertRedirect(route('login'));
});

// AUTH-2 — valid credentials authenticate the agent and land on the board.
it('logs an agent in with valid credentials', function (): void {
    $agent = User::factory()->create([
        'email' => 'agent@fluxdesk.test',
        'password' => 'password',
    ]);

    Livewire::test('auth::login')
        ->set('email', $agent->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('tickets.index'));

    $this->assertAuthenticatedAs($agent);
});

// AUTH-3 — wrong password surfaces an inline error, never authenticates.
it('rejects an agent with the wrong password', function (): void {
    $agent = User::factory()->create([
        'email' => 'agent@fluxdesk.test',
        'password' => 'password',
    ]);

    Livewire::test('auth::login')
        ->set('email', $agent->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

// AUTH-4 — logout via the session-menu component clears the session.
it('logs an authenticated agent out via the session menu', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('auth::session-menu')
        ->call('logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

// AUTH-5 — an already-authenticated agent visiting login is sent to the board.
it('redirects an authenticated agent away from the login screen', function (): void {
    $agent = User::factory()->create();

    Livewire::actingAs($agent)
        ->test('auth::login')
        ->assertRedirect(route('tickets.index'));
});
