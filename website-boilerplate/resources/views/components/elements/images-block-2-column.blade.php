<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;

}; ?>

<div>
    <div class="grid md:grid-cols-2 gap-8 py-4">
        <div>
            <div class="relative w-full aspect-[624/400] !bg-cover bg-center overflow-hidden group">
                <a @if(isset($element->videoUrl[session('selectedLanguage')]) && $element->videoUrl[session('selectedLanguage')]) href="#/" @click.prevent="openLightbox('{{ $element->videoUrl[session('selectedLanguage')] }}')" @endisset
                style="background: url('{{ env('MEDIA_URL') . ($element->image ?? '') }}')"
                   class="h-full bg-gray-300 flex transition-opacity opacity-100 transition-transform
                 @if(isset($element->videoUrl[session('selectedLanguage')]) && $element->videoUrl[session('selectedLanguage')]) hover:scale-105 @endif
                   bg-center !bg-cover">
                </a>
            </div>
        </div>
        <div>
            <div class="relative w-full aspect-[624/400] !bg-cover bg-center overflow-hidden group">
                <a @if(isset($element->videoUrl2[session('selectedLanguage')]) && $element->videoUrl2[session('selectedLanguage')]) href="#/" @click.prevent="openLightbox('{{ $element->videoUrl2[session('selectedLanguage')] }}')" @endisset
                style="background: url('{{ env('MEDIA_URL') . ($element->image2 ?? '') }}')"
                   class="h-full bg-gray-300 flex transition-opacity opacity-100 transition-transform
                   @if(isset($element->videoUrl[session('selectedLanguage')]) && $element->videoUrl[session('selectedLanguage')]) hover:scale-105 @endif
                   bg-center !bg-cover">
                </a>
            </div>
        </div>
    </div>

    <!-- Lightbox component placeholder - implement as needed -->
</div>
