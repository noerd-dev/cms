<?php

use Livewire\Volt\Component;

new class extends Component {

    public function setDE()
    {
        session(['selectedLanguage' => 'de']);
        $this->dispatch('languageChanged');
    }

    public function setEN()
    {
        session(['selectedLanguage' => 'en']);
        $this->dispatch('languageChanged');
    }
} ?>

<div class="w-full flex">
    <div class="ml-auto flex">
        <a @class([
        'cursor-pointer ml-2',
        'text-black underline' => session('selectedLanguage') === 'de',
        'text-gray-500' => session('selectedLanguage') !== 'de',
    ]) wire:click="setDE">
            DE
        </a>
        <a @class([
        'cursor-pointer ml-2',
        'text-black underline' => session('selectedLanguage') === 'en',
        'text-gray-500' => session('selectedLanguage') !== 'en',
    ]) wire:click="setEN">
            EN
        </a>
    </div>
</div>
