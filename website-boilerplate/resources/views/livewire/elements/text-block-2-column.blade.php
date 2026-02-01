<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;

}; ?>

<div class="grid md:grid-cols-2 gap-8 py-4 pt-2">
    @if(strlen($element->text1 ?? '' ) > 0)
    <div class="pb-4 text-left text-lg font-light">
        <x-noerd::markdown :content="$element->text1 ?? ''" />
    </div>
    @endif
    @if(strlen($element->text2 ?? '' ) > 0)
        <div class="pb-4 text-left text-lg font-light">
            <x-noerd::markdown :content="$element->text2 ?? ''" />
        </div>
    @endif
</div>