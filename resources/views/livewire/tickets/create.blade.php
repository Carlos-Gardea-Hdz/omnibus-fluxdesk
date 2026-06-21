<?php

use App\Domain\Ticketing\Actions\CreateTicket;
use App\Domain\Ticketing\Data\CreateTicketData;
use App\Domain\Ticketing\Enums\TicketPriority;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('FluxDesk · New ticket')]
class extends Component
{
    public string $subject = '';

    public string $body = '';

    public string $priority = TicketPriority::Medium->value;

    /**
     * Build the DTO from component state — the DTO is the validation SSOT.
     * validateAndCreate throws ValidationException, which Livewire surfaces as
     * inline field errors. Nothing persists unless the DTO is valid.
     */
    public function save(): void
    {
        $data = CreateTicketData::validateAndCreate([
            'subject' => $this->subject,
            'body' => $this->body,
            'priority' => $this->priority,
        ]);

        // Requester resolved server-side from the authenticated agent — never input.
        $ticket = app(CreateTicket::class)->handle($data, Auth::user());

        $this->redirectRoute('tickets.show', $ticket, navigate: true);
    }

    /**
     * @return array{priorities: list<TicketPriority>}
     */
    public function with(): array
    {
        return ['priorities' => TicketPriority::cases()];
    }
}; ?>

@php($lang = app()->getLocale())

<div class="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-8 flex items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('tickets.create_title') }}</flux:heading>
        <livewire:auth::session-menu />
    </header>

    <form wire:submit="save" class="space-y-6">
        <flux:input
            wire:model="subject"
            :label="__('tickets.subject')"
            required
            autofocus
        />

        <flux:textarea
            wire:model="body"
            :label="__('tickets.body')"
            rows="6"
            required
        />

        <flux:select wire:model="priority" :label="__('tickets.priority')">
            @foreach ($priorities as $case)
                <flux:select.option value="{{ $case->value }}">
                    {{ $case->label()[$lang] ?? $case->label()['en'] }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center justify-end gap-3">
            <flux:button href="{{ route('tickets.index') }}" variant="ghost" wire:navigate>
                {{ __('tickets.cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('tickets.save') }}
            </flux:button>
        </div>
    </form>
</div>
