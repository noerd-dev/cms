<?php

use Livewire\Volt\Component;

new class extends Component {
    public ?string $websiteUrl = null;

    public function mount()
    {
        $user = auth()->user();
        $tenant = $user?->selectedTenant();
        $uuid = $tenant?->uuid ?? null;

        if ($user && $user->can('canCms') && ! empty($uuid)) {
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
            <button class="bg-gray-100 rounded-lg my-auto text-sm px-3 py-1">
                Zur Webseite
            </button>
        </a>
    @endif
</div>
