<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;
}; ?>

<div class="grid lg:grid-cols-2 gap-8 pb-28">
    <div class="text-block-header-links text-2xl lg:text-3xl mt-auto uppercase font-light text-gray-900">
        @if(!empty($element->toppage))
            <a href="/{{ strtolower($element->toppage) }}">{{ $element->toppage }}</a> /
        @endif

        {{ $element->title ?? '' }}
    </div>
    <div class="text-left text-lg font-light">
        <div class="rich-text">{!! $element->description ?? '' !!}</div>
    </div>
    <style>
        .text-block-header-links a {
            color: #9f9c9e;
        }

        .text-block-header-links a:hover {
            color: #000;
        }
    </style>
</div>
