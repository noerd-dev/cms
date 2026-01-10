<?php

use Livewire\Volt\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;

}; ?>

<div class="grid lg:grid-cols-2 gap-8 pb-28 ">
    <div class="text-2xl lg:text-3xl uppercase font-light text-gray-900">
        {{ $element->title ?? '' }}
    </div>
    <div class="text-left text-lg font-light">
        <x-noerd::markdown :content="$element->description ?? ''" />
    </div>
</div>