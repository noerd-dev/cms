<?php

use Livewire\Volt\Component;
use Noerd\Website\Models\Language;
use Noerd\Website\Models\Page;

new class extends Component {

    public array $languages = [];

    public function mount($tenantId = null): void
    {
        // In real frontend context, get tenantId from middleware
        // In test context, it can be passed as parameter
        if (!$tenantId) {
            $tenantId = request()->attributes->get('tenant_id');
        }

        if ($tenantId) {
            $this->languages = Language::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['code', 'name'])
                ->toArray();

            if (!session('selectedLanguage') && count($this->languages) > 0) {
                $default = Language::where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->orderBy('sort_order')
                    ->first();
                if ($default) {
                    session(['selectedLanguage' => $default->code]);
                }
            }
        }
    }

    public function setLanguage(string $code)
    {
        $requestUrl = request()->headers->get('referer');

        $slug = parse_url($requestUrl, PHP_URL_PATH);

        $page = Page::whereJsonContains('slug->' . session('selectedLanguage'), $slug)
            ->where('tenant_id', session('selectedTenantId'))
            ->first();

        session(['selectedLanguage' => $code]);


        if ($page) {
            $slug = $page->slug;

            return redirect($slug[$code]);

        }

        redirect($slug ?? '/');

    }
} ?>
<div class="w-full flex">
    @if(count($this->languages) > 1)
        <div class="ml-auto flex">
            @foreach($this->languages as $language)
                <a @class([
                    'cursor-pointer ml-2',
                    'text-black underline' => session('selectedLanguage') === $language['code'],
                    'text-gray-500' => session('selectedLanguage') !== $language['code'],
                ]) wire:click="setLanguage('{{$language['code']}}')">
                    {{ strtoupper($language['code']) }}
                </a>
            @endforeach
        </div>
    @endif
</div>
