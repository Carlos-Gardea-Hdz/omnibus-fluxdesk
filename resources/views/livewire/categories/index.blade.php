<?php

use App\Domain\Ticketing\Actions\ArchiveCategory;
use App\Domain\Ticketing\Actions\CreateCategory;
use App\Domain\Ticketing\Actions\RestoreCategory;
use App\Domain\Ticketing\Actions\UpdateCategory;
use App\Domain\Ticketing\Data\CategoryData;
use App\Domain\Ticketing\Enums\CategoryColor;
use App\Domain\Ticketing\Exceptions\DuplicateCategoryName;
use App\Domain\Ticketing\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('FluxDesk · Categories')]
class extends Component
{
    use WithPagination;

    // Form state — bound to the create/edit modal. The DTO is the validation SSOT.
    public string $name = '';

    public string $color = '';

    public string $description = '';

    // When set, save() updates this category; when null, it creates a new one.
    public ?string $editingId = null;

    // Modal visibility, driven by edit()/resetForm().
    public bool $showModal = false;

    #[Url(history: true)]
    public bool $showArchived = false;

    public function mount(): void
    {
        // Sensible default colour so the picker is never empty on first open.
        $this->color = CategoryColor::cases()[0]->value;
    }

    public function updatingShowArchived(): void
    {
        $this->resetPage();
    }

    /**
     * Create or update via the matching Action. A duplicate name is a domain
     * concern — caught here as an inline field error, never a 500.
     */
    public function save(): void
    {
        $data = CategoryData::validateAndCreate([
            'name' => $this->name,
            'color' => $this->color,
            'description' => $this->description !== '' ? $this->description : null,
        ]);

        try {
            if ($this->editingId !== null) {
                $category = Category::query()->findOrFail($this->editingId);
                app(UpdateCategory::class)->handle($category, $data);
            } else {
                app(CreateCategory::class)->handle($data);
            }
        } catch (DuplicateCategoryName) {
            $this->addError('name', __('categories.name_taken'));

            return;
        }

        $this->resetForm();
    }

    /**
     * Loads a category into the form and opens the modal for editing.
     */
    public function edit(string $id): void
    {
        $category = Category::query()->findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->color = $category->color->value;
        $this->description = $category->description ?? '';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    /**
     * Opens a clean modal for creating a new category.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function archive(string $id): void
    {
        $category = Category::query()->findOrFail($id);
        app(ArchiveCategory::class)->handle($category);
    }

    public function restore(string $id): void
    {
        $category = Category::query()->findOrFail($id);
        app(RestoreCategory::class)->handle($category);
    }

    /**
     * Resets the form back to a pristine "create" state and closes the modal.
     */
    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->color = CategoryColor::cases()[0]->value;
        $this->description = '';
        $this->resetErrorBag();
        $this->showModal = false;
    }

    /**
     * @return LengthAwarePaginator<int, Category>
     */
    public function categories(): LengthAwarePaginator
    {
        // Active vs archived view toggle — a single bounded, paginated query.
        return Category::query()
            ->when($this->showArchived, fn ($query) => $query->archived(), fn ($query) => $query->active())
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return array{categories: LengthAwarePaginator<int, Category>, colors: list<CategoryColor>}
     */
    public function with(): array
    {
        return [
            'categories' => $this->categories(),
            'colors' => CategoryColor::cases(),
        ];
    }
}; ?>

@php($lang = app()->getLocale())

<div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('categories.title') }}</flux:heading>
            <flux:subheading>{{ __('categories.subtitle') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-4">
            <flux:button wire:click="create" variant="primary" icon="plus">
                {{ __('categories.new') }}
            </flux:button>
            <livewire:auth::session-menu />
        </div>
    </header>

    <nav class="mb-6 flex flex-wrap items-center gap-3">
        <flux:button href="{{ route('tickets.index') }}" variant="ghost" size="sm" icon="ticket" wire:navigate>
            {{ __('tickets.index_title') }}
        </flux:button>

        {{-- Active / archived view toggle. --}}
        <flux:switch
            wire:model.live="showArchived"
            :label="__('categories.show_archived')"
        />
    </nav>

    @if ($categories->isEmpty())
        <div class="rounded-xl border border-dashed border-border bg-surface-raised p-12 text-center">
            <flux:text>{{ __('categories.empty') }}</flux:text>
        </div>
    @else
        <flux:table :paginate="$categories">
            <flux:table.columns>
                <flux:table.column>{{ __('categories.name') }}</flux:table.column>
                <flux:table.column>{{ __('categories.color') }}</flux:table.column>
                <flux:table.column>{{ __('categories.description') }}</flux:table.column>
                <flux:table.column>{{ __('categories.manage') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($categories as $category)
                    <flux:table.row wire:key="category-{{ $category->id }}">
                        <flux:table.cell>
                            {{-- User input — always {{ }} auto-escaped, never {!! !!}. --}}
                            <flux:badge :color="$category->color->token()" size="sm">
                                {{ $category->name }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-text-muted">
                            {{ $category->color->display() }}
                        </flux:table.cell>
                        <flux:table.cell class="text-text-muted">
                            {{ $category->description ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button
                                    wire:click="edit('{{ $category->id }}')"
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil-square"
                                >
                                    {{ __('categories.edit') }}
                                </flux:button>

                                @if ($category->isArchived())
                                    <flux:button
                                        wire:click="restore('{{ $category->id }}')"
                                        variant="ghost"
                                        size="sm"
                                        icon="arrow-uturn-left"
                                    >
                                        {{ __('categories.restore') }}
                                    </flux:button>
                                @else
                                    <flux:button
                                        wire:click="archive('{{ $category->id }}')"
                                        variant="ghost"
                                        size="sm"
                                        icon="archive-box"
                                    >
                                        {{ __('categories.archive') }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Create / edit modal — same form for both, driven by $editingId. --}}
    <flux:modal wire:model.self="showModal" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId !== null ? __('categories.edit') : __('categories.new') }}
                </flux:heading>
            </div>

            <flux:input
                wire:model="name"
                :label="__('categories.name')"
                required
                autofocus
            />
            @error('name')
                <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            <flux:select wire:model="color" :label="__('categories.color')">
                @foreach ($colors as $case)
                    <flux:select.option value="{{ $case->value }}">
                        {{ $case->display() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="description"
                :label="__('categories.description')"
                rows="3"
            />

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="resetForm" variant="ghost">
                    {{ __('categories.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('categories.save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
