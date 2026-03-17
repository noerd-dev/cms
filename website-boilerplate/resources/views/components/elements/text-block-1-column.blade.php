<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;

}; ?>

<div class="py-4 pt-2">
    <div class="pb-4 text-left text-lg font-light">
        <div class="rich-text">{!! $element->text ?? '' !!}</div>
    </div>
</div>
