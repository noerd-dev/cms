<?php

use Livewire\Volt\Component;

new class extends Component {
    public ?string $websiteUrl = null;

    public function mount()
    {
        $user = auth()->user();
        $tenant = $user?->selectedTenant();
        $hash = $tenant?->hash ?? null;

        if ($user && $user->can('canCms') && ! empty($hash)) {
            $configuredUrl = config('noerd_cms.website_url');

            $this->websiteUrl = ! empty($configuredUrl)
                ? $configuredUrl
                : url('/index?hash=' . $hash);
        }
    }
} ?>

<div class="hidden lg:flex">
    @if($websiteUrl)
        <a class="flex" target="_blank" href="{{ $websiteUrl }}">
            <button class="bg-gray-100 rounded-lg my-auto text-sm px-3 py-1">
                {{ __('cms_to_website') }}
            </button>
        </a>
    @endif
</div>
