<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('FluxDesk · Sign in')]
class extends Component
{
    // Login is an auth concern, not a domain DTO — rules live inline via
    // #[Validate]. The requester/author identities are never client-supplied;
    // here we only authenticate the agent against their own credentials.
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * An already-authenticated agent has no business on the login screen.
     */
    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectRoute('tickets.index', navigate: true);
        }
    }

    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            // Fail loud, inline — no leak of which field was wrong.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Prevent session fixation after a privilege change.
        session()->regenerate();

        $this->redirectRoute('tickets.index', navigate: true);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), maxAttempts: 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

@php($lang = app()->getLocale())

<div class="mx-auto flex min-h-full w-full max-w-md flex-col justify-center px-4 py-12 sm:px-6">
    <header class="mb-8 text-center">
        <flux:heading size="xl">{{ __('auth.login_title') }}</flux:heading>
        <flux:subheading>{{ __('auth.login_subtitle') }}</flux:subheading>
    </header>

    <form wire:submit="login" class="space-y-6">
        <flux:input
            wire:model="email"
            type="email"
            :label="__('auth.email')"
            autocomplete="username"
            required
            autofocus
        />

        <flux:input
            wire:model="password"
            type="password"
            :label="__('auth.password')"
            autocomplete="current-password"
            required
            viewable
        />

        <flux:checkbox wire:model="remember" :label="__('auth.remember')" />

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('auth.sign_in') }}
        </flux:button>
    </form>
</div>
