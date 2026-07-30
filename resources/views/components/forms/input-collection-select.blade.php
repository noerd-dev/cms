@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'readonly' => false,
    'live' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;

    $requiredFields = $field['required_fields'] ?? [];

    $collections = \Noerd\Cms\Models\Collection::where('tenant_id', auth()->user()->selected_tenant_id)
        ->where('is_element_collection', false)
        ->orderBy('name')
        ->get();

    if (! empty($requiredFields)) {
        $collections = $collections->filter(function ($collection) use ($requiredFields) {
            $fieldNames = \Noerd\Cms\Helpers\CollectionHelper::getCollectionFieldNames($collection->collection_key);
            foreach ($requiredFields as $required) {
                if (! in_array($required, $fieldNames)) {
                    return false;
                }
            }
            return true;
        });
    }
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />
    <div class="flex">
        <select
            @if ($live)
                wire:model.live.debounce="{{ $name }}"
            @else
                wire:model="{{ $name }}"
            @endif
            {{ $readonly ? 'disabled' : '' }}
            class="focus:ring-brand-border block h-8 w-full appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-1 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:border-b-zinc-200 disabled:text-zinc-500 disabled:placeholder-zinc-400/70 disabled:shadow-none sm:text-sm"
            id="{{ $name }}"
        >
            <option value="">{{ __('Please select...') }}</option>
            @foreach ($collections as $collection)
                <option value="{{ $collection->id }}">{{ $collection->name }}</option>
            @endforeach
        </select>

        <x-noerd::button
            x-data="{ collectionKey: $wire.entangle('{{ $name }}') }"
            @click="$modal('cms::collection-entries-list', {collectionKey: collectionKey, context: '{{ $name }}'})"
            class="!mt-0 !ml-1 h-8 rounded"
            type="button"
        >
            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
        </x-noerd::button>
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
