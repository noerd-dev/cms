<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;

}; ?>

<div>
    <div class="py-4">
        <div class="overflow-hidden group">
            <a @if(isset($element->videoUrl) && $element->videoUrl) href="{{ $element->videoUrl }}" target="_blank" @else href="#" @endif
               style="background: url('{{ env('MEDIA_URL') . ($element->image ?? '') }}')"
               class="h-full bg-gray-300 flex aspect-[1280/600] !bg-cover transition-transform transition-opacity opacity-100 transition-transform

              @if(isset($element->videoUrl) && $element->videoUrl) hover:scale-105 @endif
               bg-center !bg-cover">
            </a>
        </div>
    </div>

    <!-- Lightbox component placeholder - implement as needed -->
</div>