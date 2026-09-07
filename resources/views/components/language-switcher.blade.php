<?php

use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Support\CmsLanguageCodes;

new class extends Component {
    public array $languages = [];

    public function mount(): void
    {
        $this->languages = CmsLanguage::query()
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->get(['code', 'name'])
            ->toArray();

        if (! session('selectedLanguage')) {
            session(['selectedLanguage' => CmsLanguageCodes::active()[0] ?? null]);
        }
    }

    public function setLanguage(string $code): void
    {
        session(['selectedLanguage' => $code]);
        $this->dispatch('languageChanged');
    }
} ?>
{{-- The switcher carries its own chrome, so every editor header embeds it the same way. --}}
<div>
    @if (count($languages) > 1)
        <div class="flex w-fit items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-1">
            @foreach ($languages as $language)
                <a @class([
                    'cursor-pointer text-sm',
                    'text-black underline' => session('selectedLanguage') === $language['code'],
                    'text-gray-500' => session('selectedLanguage') !== $language['code'],
                ]) wire:click="setLanguage('{{ $language['code'] }}')">
                    {{ mb_strtoupper($language['code']) }}
                </a>
            @endforeach
        </div>
    @endif
</div>
