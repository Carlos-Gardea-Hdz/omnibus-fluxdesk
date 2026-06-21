<?php

use App\Domain\Ticketing\Actions\AssignTicket;
use App\Domain\Ticketing\Actions\CommentOnTicket;
use App\Domain\Ticketing\Actions\TransitionTicket;
use App\Domain\Ticketing\Data\AssignTicketData;
use App\Domain\Ticketing\Data\CommentOnTicketData;
use App\Domain\Ticketing\Enums\TicketStatus;
use App\Domain\Ticketing\Exceptions\InvalidTicketTransition;
use App\Domain\Ticketing\Models\Category;
use App\Domain\Ticketing\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('FluxDesk · Ticket')]
class extends Component
{
    // The bound ticket id is locked — a client may not swap the target.
    #[Locked]
    public string $ticketId;

    public ?string $assigneeId = null;

    public ?string $categoryId = null;

    public string $commentBody = '';

    public function mount(Ticket $ticket): void
    {
        $this->ticketId = $ticket->id;
        $this->assigneeId = $ticket->assignee_id;
        $this->categoryId = $ticket->category_id;
    }

    /**
     * Moves the ticket along its lifecycle. An illegal move fails loud in the
     * domain and is caught here as an inline error — never a 500.
     */
    public function changeStatus(string $target): void
    {
        try {
            $status = TicketStatus::from($target);
            app(TransitionTicket::class)->handle($this->ticket, $status);
        } catch (InvalidTicketTransition|\ValueError) {
            $this->addError('transition', __('tickets.transition_illegal'));
        }
    }

    /**
     * (Re)assigns the ticket to an agent, or unassigns when blank. The DTO is
     * the validation SSOT; an invalid id surfaces as an inline error.
     */
    public function assign(): void
    {
        $data = AssignTicketData::validateAndCreate([
            'assigneeId' => $this->assigneeId !== '' ? $this->assigneeId : null,
        ]);

        $assignee = $data->assigneeId !== null
            ? User::query()->find($data->assigneeId)
            : null;

        app(AssignTicket::class)->handle($this->ticket, $assignee);
    }

    /**
     * Re-categorises the ticket (or clears it when blank). Does NOT recompute
     * due_at — the SLA clock is fixed at creation by priority. Named descriptively
     * (never transition(), which collides with Livewire\Component).
     */
    public function changeCategory(): void
    {
        $categoryId = $this->categoryId !== '' ? $this->categoryId : null;

        // Guard against archived / unknown ids — only an active category may be set.
        if ($categoryId !== null && ! Category::query()->active()->whereKey($categoryId)->exists()) {
            $this->addError('category', __('tickets.no_category'));

            return;
        }

        $this->ticket->update(['category_id' => $categoryId]);

        // Refresh the memoised instance so with() reflects the new category.
        unset($this->ticket);
    }

    /**
     * Posts a comment. Body validated via its DTO (SSOT); author resolved from
     * the authenticated agent server-side — never client input.
     */
    public function addComment(): void
    {
        $data = CommentOnTicketData::validateAndCreate([
            'body' => $this->commentBody,
        ]);

        app(CommentOnTicket::class)->handle($this->ticket, $data, auth()->user());

        $this->commentBody = '';
    }

    // Memoised per request — the action methods mutate THIS instance in place, so
    // with()'s re-render reflects the change without an extra query per call.
    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::query()
            ->with(['requester:id,name', 'assignee:id,name', 'category:id,name,color'])
            ->findOrFail($this->ticketId);
    }

    /**
     * @return Collection<int, \App\Domain\Ticketing\Models\TicketComment>
     */
    public function comments(): Collection
    {
        // Oldest first — a chronological conversation thread.
        return $this->ticket
            ->comments()
            ->with('author:id,name')
            ->oldest()
            ->get();
    }

    /**
     * @return array{
     *     ticket: Ticket,
     *     comments: Collection<int, \App\Domain\Ticketing\Models\TicketComment>,
     *     agents: Collection<int, User>,
     *     categories: Collection<int, Category>
     * }
     */
    public function with(): array
    {
        return [
            'ticket' => $this->ticket,
            'comments' => $this->comments(),
            'agents' => User::query()->orderBy('name')->get(['id', 'name']),
            // Active categories only — archived ones are not selectable.
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

@php($lang = app()->getLocale())

<div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between gap-4">
        <flux:button href="{{ route('tickets.index') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('tickets.index_title') }}
        </flux:button>
        <livewire:auth::session-menu />
    </div>

    <header class="mb-6 border-b border-border pb-6">
        <flux:heading size="xl">{{ $ticket->subject }}</flux:heading>
        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-text-muted">
            <span>{{ __('tickets.ticket_number', ['id' => $ticket->id]) }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ $ticket->created_at?->diffForHumans() }}</span>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <flux:badge :color="$ticket->status->color()" size="sm">
                {{ $ticket->status->label()[$lang] ?? $ticket->status->label()['en'] }}
            </flux:badge>
            <flux:badge :color="$ticket->priority->color()" size="sm">
                {{ $ticket->priority->label()[$lang] ?? $ticket->priority->label()['en'] }}
            </flux:badge>

            @if ($ticket->category !== null)
                {{-- Category name is user input — {{ }} auto-escaped. --}}
                <flux:badge :color="$ticket->category->color->token()" size="sm">
                    {{ $ticket->category->name }}
                </flux:badge>
            @endif

            @php($sla = $ticket->slaState())
            @if ($sla !== null)
                {{-- SLA state: colour + text together (WCAG 1.4.1). --}}
                <flux:badge :color="$sla->color()" size="sm">
                    {{ $sla->label()[$lang] ?? $sla->label()['en'] }}
                </flux:badge>
            @endif
        </div>

        @if ($ticket->due_at !== null)
            <div class="mt-2 text-sm text-text-muted">
                {{ __('tickets.due_at') }}: {{ $ticket->due_at->diffForHumans() }}
            </div>
        @endif
    </header>

    <section class="mb-8" aria-label="{{ __('tickets.description') }}">
        <flux:heading size="lg" class="mb-2">{{ __('tickets.description') }}</flux:heading>
        <flux:text class="whitespace-pre-wrap">{{ $ticket->body }}</flux:text>
    </section>

    <section class="mb-8" aria-label="{{ __('a11y.ticket_actions') }}">
        <flux:heading size="lg" class="mb-3">{{ __('tickets.transition') }}</flux:heading>

        @error('transition')
            <flux:callout variant="danger" class="mb-3">{{ $message }}</flux:callout>
        @enderror

        <div class="flex flex-wrap gap-2">
            {{-- Only legal next states are offered — the graph drives the UI. --}}
            @foreach ($ticket->status->allowedTransitions() as $next)
                <flux:button
                    wire:key="trans-{{ $next->value }}"
                    wire:click="changeStatus('{{ $next->value }}')"
                    variant="outline"
                    size="sm"
                >
                    {{ $next->label()[$lang] ?? $next->label()['en'] }}
                </flux:button>
            @endforeach
        </div>
    </section>

    <section class="mb-8" aria-label="{{ __('tickets.assignee') }}">
        <flux:heading size="lg" class="mb-3">{{ __('tickets.assignee') }}</flux:heading>
        <form wire:submit="assign" class="flex flex-wrap items-end gap-3">
            <flux:select wire:model="assigneeId" :aria-label="__('tickets.assignee')" class="min-w-56">
                <flux:select.option value="">{{ __('tickets.unassigned') }}</flux:select.option>
                @foreach ($agents as $agent)
                    <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary" size="sm">{{ __('tickets.assign') }}</flux:button>
        </form>
    </section>

    <section class="mb-8" aria-label="{{ __('tickets.category') }}">
        <flux:heading size="lg" class="mb-3">{{ __('tickets.category') }}</flux:heading>

        @error('category')
            <flux:callout variant="danger" class="mb-3">{{ $message }}</flux:callout>
        @enderror

        <form wire:submit="changeCategory" class="flex flex-wrap items-end gap-3">
            <flux:select wire:model="categoryId" :aria-label="__('tickets.category')" class="min-w-56">
                <flux:select.option value="">{{ __('tickets.no_category') }}</flux:select.option>
                @foreach ($categories as $category)
                    {{-- Category name is user input — {{ }} auto-escaped. --}}
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary" size="sm">{{ __('tickets.save') }}</flux:button>
        </form>
    </section>

    <section aria-label="{{ __('tickets.comments') }}">
        <flux:heading size="lg" class="mb-4">{{ __('tickets.comments') }}</flux:heading>

        <ul class="mb-6 space-y-4">
            @foreach ($comments as $comment)
                <li
                    wire:key="comment-{{ $comment->id }}"
                    class="rounded-lg border border-border bg-surface-raised p-4"
                >
                    <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-text">{{ $comment->author?->name ?? '—' }}</span>
                        <span class="text-text-muted">{{ $comment->created_at?->diffForHumans() }}</span>
                    </div>
                    <flux:text class="whitespace-pre-wrap">{{ $comment->body }}</flux:text>
                </li>
            @endforeach
        </ul>

        <form wire:submit="addComment" class="space-y-3">
            <flux:textarea
                wire:model="commentBody"
                :aria-label="__('tickets.comments')"
                :placeholder="__('tickets.comment_placeholder')"
                rows="3"
            />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" size="sm">
                    {{ __('tickets.add_comment') }}
                </flux:button>
            </div>
        </form>
    </section>
</div>
