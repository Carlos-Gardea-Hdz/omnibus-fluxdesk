<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    /**
     * Ends the agent session. A method (not a closure route) so the logout is
     * CSRF-protected by Livewire and routes stay closure-free (project law).
     */
    public function logout(): void
    {
        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex items-center gap-3">
    @if ($user = auth()->user())
        <flux:text class="hidden sm:inline">{{ $user->name }}</flux:text>
    @endif

    <flux:button wire:click="logout" variant="subtle" size="sm" icon="arrow-right-start-on-rectangle">
        {{ __('auth.sign_out') }}
    </flux:button>
</div>
