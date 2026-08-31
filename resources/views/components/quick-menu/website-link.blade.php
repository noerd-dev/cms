<?php

use Livewire\Component;

new class () extends Component {
    public ?string $websiteUrl = null;

    public function mount(): void
    {
        $user = auth()->user();
        $tenant = $user?->selectedTenant();
        $uuid = $tenant?->uuid ?? null;

        if ($user && \Noerd\Helpers\AccessHelper::canUseApp('CMS') && ! empty($uuid)) {
            $configuredUrl = config('noerd_cms.website_url');

            $this->websiteUrl = ! empty($configuredUrl)
                ? $configuredUrl
                : url('/index?uuid=' . $uuid);
        }
    }
} ?>

<div class="hidden lg:flex">
    @if($websiteUrl)
        <a class="flex" target="_blank" href="{{ $websiteUrl }}">
            <x-noerd::button variant="pill" >
                {{ __('To Website') }}
            </x-noerd::button>
        </a>
    @endif
</div>
