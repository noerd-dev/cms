<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Models\Language;

new class extends Component {

    public array $languages = [];

    public function mount(): void
    {
        $this->languages = Language::where('tenant_id', auth()->user()->selected_tenant_id)
            ->get(['code', 'name'])
            ->toArray();

        if (!session('selectedLanguage')) {
            $default = Language::where('tenant_id', auth()->user()->selected_tenant_id)
                ->where('is_default', true)
                ->first();
            if ($default) {
                session(['selectedLanguage' => $default->code]);
            }
        }
    }

    public function setLanguage(string $code): void
    {
        session(['selectedLanguage' => $code]);
        $this->dispatch('languageChanged');
    }
} ?>
<div class="flex">
    @if(count($languages) > 1)
        <div class="ml-auto mr-6 my-auto border-l border-gray-200 pl-4">
            <div class="ml-auto flex">
                @foreach($languages as $language)
                    <a @class([
                    'cursor-pointer ml-2',
                    'text-black underline' => session('selectedLanguage') === $language['code'],
                    'text-gray-500' => session('selectedLanguage') !== $language['code'],
                ]) wire:click="setLanguage('{{$language['code']}}')">
                        {{ strtoupper($language['code']) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
