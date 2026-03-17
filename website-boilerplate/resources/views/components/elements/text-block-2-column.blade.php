<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;

}; ?>

<div class="grid md:grid-cols-2 gap-8 py-4 pt-2">
    @if(strlen($element->text1 ?? '' ) > 0)
    <div class="pb-4 text-left text-lg font-light">
        <div class="rich-text">{!! $element->text1 ?? '' !!}</div>
    </div>
    @endif
    @if(strlen($element->text2 ?? '' ) > 0)
        <div class="pb-4 text-left text-lg font-light">
            <div class="rich-text">{!! $element->text2 ?? '' !!}</div>
        </div>
    @endif
</div>
