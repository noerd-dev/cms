<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;

}; ?>

<div class="grid lg:grid-cols-2 gap-8 pb-28 ">
    <div class="text-2xl lg:text-3xl uppercase font-light text-gray-900">
        {{ $element->title ?? '' }}
    </div>
    <div class="text-left text-lg font-light">
        <div class="rich-text">{!! $element->description ?? '' !!}</div>
    </div>
</div>
