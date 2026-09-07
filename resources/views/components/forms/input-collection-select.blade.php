@props([
    'field' => [],
])

@php
    /*
     | Renders through the active theme's select element (compact/numbered
     | aware); the options come from the field type resolver. The magnifier
     | opens the entries of the selected collection.
     */
    $name = $field['name'] ?? '';
    $theme = \Noerd\Support\ThemeContext::current() ?? 'default';
    $selectField = $field + ['placeholder' => $field['placeholder'] ?? 'Please select...'];
@endphp

<div class="flex items-end gap-1">
    <div class="min-w-0 flex-1">
        @include(\Noerd\Support\ThemeElementResolver::resolveElement('input-select', $theme) ?? 'themes::default.input-select', ['field' => $selectField])
    </div>

    <x-noerd::button
        x-data="{ collectionKey: $wire.entangle('{{ $name }}') }"
        @click="$modal('cms::collection-entries-list', {collectionKey: collectionKey, context: '{{ $name }}'})"
        class="!mt-0"
        type="button"
    >
        <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
    </x-noerd::button>
</div>
