<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;

}; ?>

<div class="grid grid-cols-2 text-lg lg:grid-cols-4 gap-10 py-4 pt-2">
    <div><x-noerd::markdown :content="$element->text1 ?? ''" /></div>
    <div class="text-gray-500 lg:col-span-3"><x-noerd::markdown :content="$element->text2 ?? ''" /></div>
</div>