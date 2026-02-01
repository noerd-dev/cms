<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Traits\Noerd;

new class () extends Component {
    use Noerd;

    protected const COMPONENT = 'cms-dashboard';

    #[Locked]
    public $clientId = null;

    public function with()
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

    <div class="mb-12">
        <div class="font-semibold text-sm border-b border-gray-300 pb-2">
            {{ __('cms_overview') }}
        </div>
        <div class="flex">
            <x-noerd::dashboard-card heroicon="document" title="Seiten" :value="$pagesCount"
                                     component="pages-list"/>
            <x-noerd::dashboard-card heroicon="list-bullet" title="Navigation" :value="$navigationCount"
                                     component="navigation-list"/>
        </div>
    </div>

</x-noerd::page>

