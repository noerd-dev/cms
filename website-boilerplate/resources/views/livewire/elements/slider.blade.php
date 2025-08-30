<?php

use Livewire\Volt\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;
}; ?>

<div class="py-4 pt-2 flex">

    <div class="w-full">
        <h3 class="text-xl font-semibold mb-4">Slider</h3>
        {{ $element->doesNotExist ?? '' }}

        <div class="grid gap-4">
            @foreach($this->collection('sliders') as $slider)
                <div class="p-4 border rounded-lg">
                    <h4 class="font-medium">{{ $slider['data']['title'] ?? 'Untitled' }}</h4>
                    @if(isset($slider['data']['description']))
                        <p class="text-gray-600 mt-2">{{ $slider['data']['description'] }}</p>
                    @endif
                    @if(isset($slider['data']['image']))
                        <img src="{{ $slider['data']['image'] }}" alt="{{ $slider['data']['title'] ?? 'Slider Image' }}"
                             class="mt-2 max-w-sm rounded">
                    @endif
                </div>
            @endforeach
        </div>

    </div>
</div>
