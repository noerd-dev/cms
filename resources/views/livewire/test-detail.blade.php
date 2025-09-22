<?php

use Noerd\Noerd\Traits\Noerd;
use Livewire\Volt\Component;

new class () extends Component {
    use Noerd;

    public const COMPONENT = 'test-detail';
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Page') }}</x-noerd::modal-title>
    </x-slot:header>
</x-noerd::page>
