<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;

}; ?>

<div class="grid grid-cols-2 text-lg lg:grid-cols-4 gap-10 py-4 pt-2">
    <div><div class="rich-text">{!! $element->text1 ?? '' !!}</div></div>
    <div class="text-gray-500 lg:col-span-3"><div class="rich-text">{!! $element->text2 ?? '' !!}</div></div>
</div>