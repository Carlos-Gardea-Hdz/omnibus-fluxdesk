<?php

use App\Domain\Ticketing\Enums\TicketPriority;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('FluxDesk · Tickets')]
class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $priority = '';

    // Resetting to page 1 on any filter change keeps the paginator honest.
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPriority(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function tickets(): LengthAwarePaginator
    {
        // Eager-load only the columns the table renders — never N+1, never SELECT *.
        return Ticket::query()
            ->with(['requester:id,name', 'assignee:id,name'])
            ->when($this->search !== '', fn ($query) => $query->where('subject', 'ilike', '%'.$this->search.'%'))
            ->when($this->validStatus() !== null, fn ($query) => $query->where('status', $this->status))
            ->when($this->validPriority() !== null, fn ($query) => $query->where('priority', $this->priority))
            ->latest()
            ->paginate(15);
    }

    private function validStatus(): ?TicketStatus
    {
        return TicketStatus::tryFrom($this->status);
    }

    private function validPriority(): ?TicketPriority
    {
        return TicketPriority::tryFrom($this->priority);
    }

    /**
     * @return array{tickets: LengthAwarePaginator<int, Ticket>, statuses: list<TicketStatus>, priorities: list<TicketPriority>}
     */
    public function with(): array
    {
        return [
            'tickets' => $this->tickets(),
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
        ];
    }
}; ?>

@php($lang = app()->getLocale())

<div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('tickets.index_title') }}</flux:heading>
            <flux:subheading>{{ __('tickets.index_subtitle') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-4">
            <flux:button href="{{ route('tickets.create') }}" variant="primary" icon="plus" wire:navigate>
                {{ __('tickets.new') }}
            </flux:button>
            <livewire:auth::session-menu />
        </div>
    </header>

    <div
        role="search"
        aria-label="{{ __('a11y.filters') }}"
        class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3"
    >
        <flux:input
            wire:model.live.debounce.300ms="search"
            type="search"
            icon="magnifying-glass"
            :placeholder="__('tickets.search')"
            :aria-label="__('tickets.search')"
        />

        <flux:select wire:model.live="status" :aria-label="__('tickets.status')">
            <flux:select.option value="">{{ __('tickets.all_statuses') }}</flux:select.option>
            @foreach ($statuses as $case)
                <flux:select.option value="{{ $case->value }}">
                    {{ $case->label()[$lang] ?? $case->label()['en'] }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="priority" :aria-label="__('tickets.priority')">
            <flux:select.option value="">{{ __('tickets.all_priorities') }}</flux:select.option>
            @foreach ($priorities as $case)
                <flux:select.option value="{{ $case->value }}">
                    {{ $case->label()[$lang] ?? $case->label()['en'] }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($tickets->isEmpty())
        <div class="rounded-xl border border-dashed border-border bg-surface-raised p-12 text-center">
            <flux:text>{{ __('tickets.empty') }}</flux:text>
        </div>
    @else
        <flux:table :paginate="$tickets">
            <flux:table.columns>
                <flux:table.column>{{ __('tickets.subject') }}</flux:table.column>
                <flux:table.column>{{ __('tickets.requester') }}</flux:table.column>
                <flux:table.column>{{ __('tickets.assignee') }}</flux:table.column>
                <flux:table.column>{{ __('tickets.priority') }}</flux:table.column>
                <flux:table.column>{{ __('tickets.status') }}</flux:table.column>
                <flux:table.column>{{ __('tickets.created') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($tickets as $ticket)
                    <flux:table.row wire:key="ticket-{{ $ticket->id }}">
                        <flux:table.cell>
                            <flux:link href="{{ route('tickets.show', $ticket) }}" wire:navigate>
                                {{ $ticket->subject }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->requester?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $ticket->assignee?->name ?? __('tickets.unassigned') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{-- Colour + text together, never colour alone (WCAG 1.4.1). --}}
                            <flux:badge :color="$ticket->priority->color()" size="sm">
                                {{ $ticket->priority->label()[$lang] ?? $ticket->priority->label()['en'] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$ticket->status->color()" size="sm">
                                {{ $ticket->status->label()[$lang] ?? $ticket->status->label()['en'] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-text-muted">
                            {{ $ticket->created_at?->diffForHumans() }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
