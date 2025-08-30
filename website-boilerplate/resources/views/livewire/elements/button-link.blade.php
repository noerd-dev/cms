<?php

use Livewire\Volt\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;

}; ?>

<div class="py-4 pt-2 flex">
    <a class="flex mx-auto my-auto " href="{{ $element->link ?? '#' }}"
       @if($element->external ?? false) target="_blank" @endif>
        <button
            class="inline-flex items-center gap-2 px-4 py-1.5 !bg-black rounded-xs text-white hover:bg-neutral-900 active:bg-neutral-900 transition ease-in-out duration-150 focus:outline-hidden focus:ring-2 focus:ring-neutral-500 focus:ring-offset-2 disabled:opacity-25">
            {!! $element->text ?? '' !!}
        </button>
    </a>
</div>