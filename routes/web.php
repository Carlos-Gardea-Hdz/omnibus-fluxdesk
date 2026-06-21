<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Full-page Livewire 4 components (SFCs). Route::livewire() wires each page;
// never Route::get() for full-page components (livewire-tall rule).

// Public — the only route a guest may reach. The `auth` middleware redirects
// unauthenticated visitors here (route named `login`).
Route::livewire('/login', 'auth::login')->name('login');

// Helpdesk — gated to authenticated agents (any User is an agent this slice).
Route::middleware('auth')->group(function (): void {
    Route::livewire('/', 'pages::dashboard')->name('dashboard');
    Route::livewire('/tickets', 'tickets::index')->name('tickets.index');
    Route::livewire('/tickets/create', 'tickets::create')->name('tickets.create');
    Route::livewire('/tickets/{ticket}', 'tickets::show')->name('tickets.show');
});
