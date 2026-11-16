<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;

}; ?>

@php
    // Editor data: the video link and the image URL pass the safe-URL rule;
    // the image is encoded for the CSS url().
    $videoUrl = \Noerd\Cms\Support\SafeUrl::orNull($element->videoUrl ?? null);
    $imageUrl = \Noerd\Cms\Support\SafeUrl::cssUrl(config('website.media_url') . ($element->image ?? ''));
@endphp

<div>
    <div class="py-4">
        <div class="overflow-hidden group">
            <a @if($videoUrl) href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer" @else href="#" @endif
               style="background: url('{{ $imageUrl }}')"
               class="h-full bg-gray-300 flex aspect-[1280/600] !bg-cover transition-transform transition-opacity opacity-100 transition-transform
               @if($videoUrl) hover:scale-105 @endif
               bg-center !bg-cover">
            </a>
        </div>
    </div>

    <!-- Lightbox component placeholder - implement as needed -->
</div>
