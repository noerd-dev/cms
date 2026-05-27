<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;

new class () extends Component {
    #[Locked]
    public $clientId = null;

    public function with(): array
    {
        $tenantId = Auth::user()->selected_tenant_id;

        $pagesCount = Page::where('tenant_id', $tenantId)->count();
        $navigationCount = Navigation::where('tenant_id', $tenantId)->count();
        $globalParametersCount = GlobalParameter::where('tenant_id', $tenantId)->count();

        // Get active/published pages
        $activePages = Page::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        // Get recent pages (last 7 days)
        $recentPages = Page::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'pagesCount' => $pagesCount,
            'navigationCount' => $navigationCount,
            'globalParametersCount' => $globalParametersCount,
            'activePages' => $activePages,
            'recentPages' => $recentPages,
        ];
    }
} ?>

<x-noerd::page>

    <div class="my-12">
        <div class="font-semibold text-sm border-b border-gray-300 pb-2">
            {{ __('Overview') }}
        </div>
        <div class="flex">
            <x-noerd::dashboard-card heroicon="document" title="Seiten" :value="$pagesCount"
                                     component="cms::pages-list"/>
            <x-noerd::dashboard-card heroicon="list-bullet" title="Navigation" :value="$navigationCount"
                                     component="cms::navigation-list"/>
        </div>
    </div>

</x-noerd::page>

