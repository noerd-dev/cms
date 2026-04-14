<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Models\Page;

new class extends Component
{
    public string $fieldName = '';

    public string $label = '';

    public ?int $value = null;

    public bool $required = false;

    public string $displayTitle = '';

    public function mount(string $fieldName, string $label = '', ?int $value = null, bool $required = false): void
    {
        $this->fieldName = $fieldName;
        $this->label = $label;
        $this->value = $value;
        $this->required = $required;

        if ($this->value) {
            $this->resolveTitle();
        }
    }

    #[On('pageSelected')]
    public function pageSelected($value, ?string $context = null): void
    {
        if ($context && $context !== $this->fieldName) {
            return;
        }

        $page = Page::find($value);
        if (! $page) {
            return;
        }

        $this->value = $page->id;
        $this->resolveTitle($page);

        $this->dispatch('setFieldValue',
            field: $this->fieldName,
            value: $page->id,
            relationTitle: $this->displayTitle,
        );
    }

    public function clear(): void
    {
        $this->value = null;
        $this->displayTitle = '';

        $this->dispatch('setFieldValue',
            field: $this->fieldName,
            value: null,
            relationTitle: '',
        );
    }

    public function openDetail(): void
    {
        if ($this->value) {
            $this->dispatch(
                event: 'noerdModal',
                modalComponent: 'page-detail',
                arguments: ['modelId' => $this->value],
            );
        }
    }

    protected function resolveTitle(?Page $page = null): void
    {
        $page ??= Page::find($this->value);
        if (! $page) {
            return;
        }

        $name = is_string($page->name) ? json_decode($page->name, true) : ($page->name ?? []);
        $lang = session('selectedLanguage', 'de');
        $this->displayTitle = $name[$lang] ?? array_values(array_filter($name))[0] ?? '';
    }
}; ?>

<div>
    <x-noerd::input-label for="{{ $fieldName }}" :value="__($label)" :required="$required"/>
    <div class="flex">
        <input
            class="w-full cursor-pointer border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-8 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
            type="text"
            readonly
            id="{{ $fieldName }}"
            @click="@if($displayTitle) $wire.openDetail() @else $modal('pages-list', {id: null, context: '{{ $fieldName }}', listActionMethod: 'selectAction'}) @endif"
            value="{{ $displayTitle }}"
        >

        @if($displayTitle)
            <button
                wire:click="clear"
                class="h-8 inline-flex items-center px-2 !mt-0 !ml-1 text-zinc-400 hover:text-zinc-600"
                type="button"
            >
                <x-noerd::icons.x-mark class="w-5 h-5"></x-noerd::icons.x-mark>
            </button>
        @endif

        <x-noerd::button
            @click="$modal('pages-list', {id: null, context: '{{ $fieldName }}', listActionMethod: 'selectAction'})"
            class="h-8 rounded !mt-0 !ml-1"
            type="button"
        >
            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
        </x-noerd::button>
    </div>
</div>
