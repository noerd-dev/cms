<?php

use Livewire\Volt\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;

}; ?>

<div class="grid lg:grid-cols-2 gap-8 pb-28 ">
    <div class="text-2xl auswall-kopf lg:text-3xl mt-auto uppercase font-light text-gray-900">
        @isset($element->toppage)
            @if($element->toppage)
                @if(session('selectedLanguage') == 'en')

                    @if(strtolower($element->toppage) == 'projekte')
                        <a href="/projects">Projects</a> /
                    @endif

                    @if(strtolower($element->toppage) == 'leistungen')
                        <a href="/services">Services</a> /
                    @endif

                    @if(strtolower($element->toppage) == 'kontakt')
                        <a href="/contact">Contact</a> /
                    @endif

                @else
                    <a href="/{{strtolower($element->toppage)}}">{{$element->toppage}}</a> /
                @endif

            @endif
        @endisset

        {!! $element->title ?? '' !!}

    </div>
    <div class="text-left text-lg font-light">
        {!! $element->description ?? '' !!}
    </div>
    <style>
        .auswall-kopf a {
            color: #9f9c9e;
        }

        .auswall-kopf a:hover {
            color: #000;
        }
    </style>
</div>