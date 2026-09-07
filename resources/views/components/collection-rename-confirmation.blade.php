<?php

use Livewire\Component;

new class extends Component {
    /** @var array<string, string> old field name => new field name */
    public array $renames = [];

    public function confirm(): void
    {
        $this->dispatch('collectionRenameConfirmed');
        $this->dispatch('closeTopModal');
    }

    public function skip(): void
    {
        $this->dispatch('collectionRenameSkipped');
        $this->dispatch('closeTopModal');
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Rename fields in entries?') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="null" :showBlock="false">
        <x-slot:tab1>
            <p class="text-sm text-gray-600 mb-4">{{ __('The following fields were renamed. Should the corresponding data in all existing entries of this collection be updated as well?') }}</p>
            <ul class="text-sm text-gray-700 mb-4 space-y-1">
                @foreach($renames as $oldName => $newName)
                    <li class="flex items-center gap-2">
                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $oldName }}</span>
                        <x-icon name="arrow-right" class="h-4 w-4 text-gray-400 shrink-0" />
                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $newName }}</span>
                    </li>
                @endforeach
            </ul>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <div class="ml-auto flex items-center gap-2">
            <x-noerd::button variant="secondary" wire:click="skip">
                {{ __('No, save definition only') }}
            </x-noerd::button>
            <x-noerd::button variant="primary" wire:click="confirm">
                {{ __('Yes, update entries') }}
            </x-noerd::button>
        </div>
    </x-slot:footer>
</x-noerd::page>
