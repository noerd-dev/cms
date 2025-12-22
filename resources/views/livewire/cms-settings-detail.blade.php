<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const ID = 'cmsSettingsId';
    public const COMPONENT = 'cms-settings-detail';

    public $model = [
        'homepage_page_id' => null,
        'google_analytics_id' => null,
        'show_cookie_banner' => false,
    ];

    public function mount(): void
    {
        $tenantId = auth()->user()?->selected_tenant_id;
        $settings = CmsSetting::query()->firstOrCreate(['tenant_id' => $tenantId]);
        $this->model['homepage_page_id'] = $settings->homepage_page_id;
        $this->model['google_analytics_id'] = $settings->google_analytics_id;
        $this->model['show_cookie_banner'] = $settings->show_cookie_banner ?? false;
    }

    public function store(): void
    {
        $this->validate([
            'model.homepage_page_id' => ['nullable', 'exists:pages,id'],
            'model.google_analytics_id' => ['nullable', 'string', 'max:50'],
            'model.show_cookie_banner' => ['boolean'],
        ]);

        $tenantId = auth()->user()->selected_tenant_id;

        // Ensure one row per tenant: overwrite existing settings instead of inserting new rows
        $model = CmsSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'tenant_id' => $tenantId,
                'homepage_page_id' => $this->model['homepage_page_id'],
                'google_analytics_id' => $this->model['google_analytics_id'],
                'show_cookie_banner' => $this->model['show_cookie_banner'],
            ]
        );

        $this->storeProcess($model);
        $this->dispatch('toast', [
            'title' => __('cms_saved'),
            'description' => __('cms_settings_saved'),
        ]);
    }

    public function formatName($value): string
    {
        // Accept array or JSON-string, fallback to raw string
        $names = [];
        if (is_array($value)) {
            $names = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $names = $decoded;
            } else {
                return $value;
            }
        }

        $parts = [];
        foreach ($names as $lang => $text) {
            if (is_string($text) && $text !== '') {
                $parts[] = $lang . ': ' . $text;
            }
        }

        return implode(' ', $parts);
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Settings') }}</x-noerd::modal-title>
    </x-slot:header>

    <div class="pt-4">
        <x-noerd::forms.input-select
            name="model.homepage_page_id"
            label="{{ __('Homepage') }}"
            :options="array_merge(
                [['value' => '', 'label' => '- ' . __('None selected') . ' -']],
                Page::where('collection_id', null)->orderBy('name')->get()->map(fn($p) => ['value' => $p->id, 'label' => $this->formatName($p->name)])->toArray()
            )"
        />
    </div>

    <div class="pt-4">
        <x-noerd::forms.checkbox
            name="model.show_cookie_banner"
            label="{{ __('cms_show_cookie_banner') }}"
            live
        />
        <p class="text-sm text-gray-500 mt-1">{{ __('cms_cookie_banner_required') }}</p>
    </div>

    @if($model['show_cookie_banner'] ?? false)
        <div class="pt-4">
            <x-noerd::forms.input
                name="model.google_analytics_id"
                label="{{ __('cms_google_analytics') }}"
            />
            <p class="text-sm text-gray-500 mt-1">{{ __('cms_google_analytics_hint') }}</p>
        </div>
    @endif

    <x-slot:footer>
        <x-noerd::delete-save-bar showDelete="false"/>
    </x-slot:footer>
</x-noerd::page>


