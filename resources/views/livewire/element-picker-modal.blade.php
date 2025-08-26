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
        <div class="grid grid-cols-3 gap-8">
            @foreach($this->elements() as $element)
                <div wire:click="pick('{{$element->element_key}}')"
                     class="text-sm hover:bg-gray-200 bg-gray-100 cursor-pointer border-dotted border p-4 text-center">
                    <div class="font-bold"> {{$element->name}} </div>
                    {{$element->description}}
                </div>
            @endforeach
        </div>
    </div>

</x-noerd::page>

