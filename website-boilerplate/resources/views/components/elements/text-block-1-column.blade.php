<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;

}; ?>

<div class="py-4 pt-2">
    <div class="pb-4 text-left text-lg font-light">
        <x-noerd::markdown :content="$element->text ?? ''" />
    </div>
</div>