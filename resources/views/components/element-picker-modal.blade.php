<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Helpers\FieldHelper;

new class extends Component {
    public bool $disableModal = false;

    public ?string $token = null;

    #[Computed]
    public function elements()
    {
        return FieldHelper::getAllElements();
    }

    #[Computed]
    public function groupedElements()
    {
        return FieldHelper::getAllElementsGrouped();
    }

    public function pick(string $elementKey): void
    {
        $this->dispatch('elementPicked', elementKey: $elementKey, token: $this->token);
        $this->dispatch('closeTopModal');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Add Element') }}</x-noerd::modal-title>
    </x-slot:header>

    @php
        $searchIndex = [];
        foreach ($this->groupedElements() as $groupName => $groupElements) {
            foreach ($groupElements as $element) {
                $searchIndex[$element->element_key] = mb_strtolower($element->name . ' ' . $element->description);
            }
        }
    @endphp

    <div class="mt-4" x-data="{
        search: '',
        index: {{ Js::from($searchIndex) }},
        matches(key) {
            if (this.search === '') return true;
            return this.index[key]?.includes(this.search.toLowerCase()) ?? false;
        }
    }">
        <div class="mb-4">
            <input type="text"
                   x-model="search"
                   x-init="$nextTick(() => $el.focus())"
                   placeholder="{{ __('Search elements...') }}"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none">
        </div>

        @foreach($this->groupedElements() as $groupName => $groupElements)
            <div class="mb-8"
                 x-show="search === '' || [{{ collect($groupElements)->map(fn($el) => "'" . $el->element_key . "'")->join(', ') }}].some(key => matches(key))">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                    {{ $groupName }}
                </h3>
                <div class="grid grid-cols-3 gap-4">
                    @foreach($groupElements as $element)
                        <div wire:click="pick('{{$element->element_key}}')"
                             x-show="matches('{{ $element->element_key }}')"
                             class="text-sm hover:bg-gray-200 bg-gray-100 cursor-pointer border-dotted border p-4 text-center rounded-md transition-colors">
                            <div class="font-bold mb-1">{{$element->name}}</div>
                            <div class="text-gray-600 text-xs">{{$element->description}}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-noerd::page>





