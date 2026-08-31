@props([
    'field' => null,
    'name' => '',
    'label' => '',
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;

    $pages = \Noerd\Cms\Models\Page::whereNull('collection_id')
        ->orderBy('name')
        ->get();
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" />
    <select
        wire:model="{{ $name }}"
        id="{{ $name }}"
        class="focus:ring-brand-border block h-8 w-full appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-1 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
    >
        <option value="">- {{ __('None selected') }} -</option>
        @foreach ($pages as $page)
            <option value="{{ $page->id }}">{{ \Noerd\Support\RelationFieldDefinition::normalizeDisplayValue($page->name) }}</option>
        @endforeach
    </select>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
