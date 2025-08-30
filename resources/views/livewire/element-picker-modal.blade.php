<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'element-picker-modal';

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
        $this->dispatch('close-modal-' . self::COMPONENT);
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Add Element') }}</x-noerd::modal-title>
    </x-slot:header>

    <div class="mt-4">
        @foreach($this->groupedElements() as $groupName => $groupElements)
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                    {{ $groupName }}
                </h3>
                <div class="grid grid-cols-3 gap-4">
                    @foreach($groupElements as $element)
                        <div wire:click="pick('{{$element->element_key}}')"
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





