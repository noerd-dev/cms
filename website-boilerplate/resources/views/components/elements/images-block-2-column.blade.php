<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;

}; ?>

@php
    // Editor data: video links pass the safe-URL rule and reach the Alpine
    // expression through Js::from() — never inside a quoted string, where a
    // quote in the value would end it. Images are encoded for the CSS url().
    $columns = [];
    foreach ([['image', 'videoUrl'], ['image2', 'videoUrl2']] as [$imageField, $videoField]) {
        $columns[] = [
            'image' => \Noerd\Cms\Support\SafeUrl::cssUrl(config('website.media_url') . ($element->{$imageField} ?? '')),
            'video' => \Noerd\Cms\Support\SafeUrl::orNull($element->{$videoField} ?? null),
        ];
    }
@endphp

<div>
    <div class="grid md:grid-cols-2 gap-8 py-4">
        @foreach ($columns as $column)
            <div>
                <div class="relative w-full aspect-[624/400] !bg-cover bg-center overflow-hidden group">
                    <a @if($column['video']) href="#/" @click.prevent="openLightbox({{ \Illuminate\Support\Js::from($column['video']) }})" @endif
                       style="background: url('{{ $column['image'] }}')"
                       class="h-full bg-gray-300 flex transition-opacity opacity-100 transition-transform
                       @if($column['video']) hover:scale-105 @endif
                       bg-center !bg-cover">
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Lightbox component placeholder - implement as needed -->
</div>
