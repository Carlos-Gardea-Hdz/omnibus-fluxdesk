<?php

use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Models\Ticket;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('FluxDesk · Dashboard')]
class extends Component
{
    /**
     * Counts of tickets by status. Memoized per request via #[Computed] to
     * avoid duplicate queries within a single render.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        // Single grouped aggregate query — never N+1.
        $rows = Ticket::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];
        foreach (TicketStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $counts;
    }
}; ?>

@php($lang = app()->getLocale())

<div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-8 flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('dashboard.title') }}</flux:heading>
            <flux:subheading>{{ __('dashboard.subtitle') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-4">
            {{-- Light / dark / system control (Flux-managed appearance). --}}
            <flux:radio.group
                x-data
                variant="segmented"
                x-model="$flux.appearance"
                aria-label="{{ __('a11y.appearance') }}"
            >
                <flux:radio value="light" icon="sun" aria-label="{{ __('appearance.light') }}" />
                <flux:radio value="dark" icon="moon" aria-label="{{ __('appearance.dark') }}" />
                <flux:radio value="system" icon="computer-desktop" aria-label="{{ __('appearance.system') }}" />
            </flux:radio.group>

            <livewire:auth::session-menu />
        </div>
    </header>

    <nav class="mb-6">
        <flux:button href="{{ route('tickets.index') }}" variant="primary" icon="ticket" wire:navigate>
            {{ __('tickets.index_title') }}
        </flux:button>
    </nav>

    <section
        aria-label="{{ __('dashboard.summary') }}"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
    >
        @foreach (TicketStatus::cases() as $status)
            <div
                wire:key="count-{{ $status->value }}"
                class="rounded-xl border border-border bg-surface-raised p-5"
            >
                <div class="flex items-center justify-between">
                    {{-- State conveyed by text + badge, never colour alone (WCAG 1.4.1). --}}
                    <flux:badge :color="$status->color()" size="sm">
                        {{ $status->label()[$lang] ?? $status->label()['en'] }}
                    </flux:badge>
                    <span class="text-3xl font-semibold tabular-nums text-text">
                        {{ $this->counts[$status->value] }}
                    </span>
                </div>
            </div>
        @endforeach
    </section>
</div>
