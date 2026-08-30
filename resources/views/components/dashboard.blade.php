<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;

new class () extends Component {
    public function with(): array
    {
        $tenantId = Auth::user()->selected_tenant_id;

        return [
            'pagesCount' => Page::where('tenant_id', $tenantId)->count(),
            'navigationCount' => Navigation::where('tenant_id', $tenantId)->count(),
        ];
    }
} ?>

<x-noerd::page>

    <div class="my-12">
        <div class="font-semibold text-sm border-b border-gray-300 pb-2">
            {{ __('Overview') }}
        </div>
        <div class="flex">
            <x-noerd::dashboard-card heroicon="document" title="{{ __('Pages') }}" :value="$pagesCount"
                                     component="cms::pages-list"/>
            <x-noerd::dashboard-card heroicon="list-bullet" title="Navigation" :value="$navigationCount"
                                     component="cms::navigation-list"/>
        </div>
    </div>

</x-noerd::page>
