<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Full-page Livewire 4 component (SFC). Route::livewire() wires the page;
// never Route::get() for full-page components (livewire-tall rule).
Route::livewire('/', 'pages::dashboard')->name('dashboard');
