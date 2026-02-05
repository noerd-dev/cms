<?php

use Livewire\Component;

new class () extends Component {
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Page') }}</x-noerd::modal-title>
    </x-slot:header>
</x-noerd::page>
