<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;
}; ?>

<div class="py-8">
    @if(!empty($element->headline))
        <h2 class="text-2xl font-bold mb-6">{{ $element->headline }}</h2>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($this->collection('services') as $entry)
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                @if(!empty($entry['image']))
                    <img src="{{ $entry['image'] }}" alt="{{ $entry['name'] ?? '' }}" class="w-full h-48 object-cover">
                @endif
                <div class="p-6">
                    @if(!empty($entry['name']))
                        <h3 class="text-lg font-semibold">{{ $entry['name'] }}</h3>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
