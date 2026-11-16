<?php

use Livewire\Component;
use Noerd\Cms\Support\SafeUrl;
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

            if (! session(\Noerd\Website\Support\WebsiteLanguage::SESSION_KEY) && count($this->languages) > 0) {
                $default = Language::where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->orderBy('sort_order')
                    ->first();
                if ($default) {
                    session([\Noerd\Website\Support\WebsiteLanguage::SESSION_KEY => $default->code]);
                }
            }
        }
    }

    public function setLanguage(string $code)
    {
        // Only a language this switcher offers (an active language of the
        // tenant) — the code is client input.
        if (! in_array($code, array_column($this->languages, 'code'), true)) {
            return null;
        }

        // The page the visitor came from — only its PATH, and only when it is
        // a path of this site (a referer `https://site//evil.com` has the path
        // `//evil.com`, which a redirect would send to another host).
        $path = parse_url((string) request()->headers->get('referer'), PHP_URL_PATH);
        $path = SafeUrl::isSitePath($path) ? $path : '/';

        $currentLanguage = (string) \Noerd\Website\Support\WebsiteLanguage::current();
        $page = preg_match('/^[a-z]{2,8}(-[a-z0-9]{2,8})?$/i', $currentLanguage)
            ? Page::whereJsonContains('slug->' . $currentLanguage, $path)
                ->where('tenant_id', session('selectedTenantId'))
                ->where('is_active', true)
                ->first()
            : null;

        session([\Noerd\Website\Support\WebsiteLanguage::SESSION_KEY => $code]);

        // Redirect to the page's slug in the target language when it exists;
        // otherwise stay on (or fall back to) the start page.
        $target = is_array($page?->slug) ? ($page->slug[$code] ?? null) : null;

        return redirect(SafeUrl::isSitePath($target) ? $target : $path);
    }
} ?>
<div class="w-full flex">
    @if(count($this->languages) > 1)
        <div class="ml-auto flex">
            @foreach($this->languages as $language)
                <a @class([
                    'cursor-pointer ml-2',
                    'text-black underline' => \Noerd\Website\Support\WebsiteLanguage::current() === $language['code'],
                    'text-gray-500' => \Noerd\Website\Support\WebsiteLanguage::current() !== $language['code'],
                ]) wire:click="setLanguage({{ \Illuminate\Support\Js::from($language['code']) }})">
                    {{ strtoupper($language['code']) }}
                </a>
            @endforeach
        </div>
    @endif
</div>
