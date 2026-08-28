<?php

use Livewire\Component;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
new class extends Component {

    public bool $showSuccessIndicator = false;

    public array $detailData = [
        'homepage_page_id' => null,
        'google_analytics_id' => null,
        'show_cookie_banner' => false,
        'cookie_lifetime_days' => 182,
        'form_recipients' => null,
    ];

    public function mount(): void
    {
        $tenantId = auth()->user()?->selected_tenant_id;
        $settings = CmsSetting::query()->firstOrCreate(['tenant_id' => $tenantId]);
        $this->detailData['homepage_page_id'] = $settings->homepage_page_id;
        $this->detailData['google_analytics_id'] = $settings->google_analytics_id;
        $this->detailData['show_cookie_banner'] = $settings->show_cookie_banner ?? false;
        $this->detailData['cookie_lifetime_days'] = $settings->cookieLifetimeInDays();
        $this->detailData['form_recipients'] = $settings->form_recipients;
    }

    public function store(): void
    {
        $this->validate([
            'detailData.homepage_page_id' => ['nullable', 'exists:pages,id'],
            'detailData.google_analytics_id' => ['nullable', 'string', 'max:50'],
            'detailData.show_cookie_banner' => ['boolean'],
            'detailData.cookie_lifetime_days' => ['required', 'integer', 'min:1', 'max:365'],
            'detailData.form_recipients' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail(__(':email is not a valid email address.', ['email' => $email]));
                    }
                }
            }],
        ]);

        $tenantId = auth()->user()->selected_tenant_id;

        // Ensure one row per tenant: overwrite existing settings instead of inserting new rows
        $cmsSettings = CmsSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'tenant_id' => $tenantId,
                'homepage_page_id' => $this->detailData['homepage_page_id'],
                'google_analytics_id' => $this->detailData['google_analytics_id'],
                'show_cookie_banner' => $this->detailData['show_cookie_banner'],
                'cookie_lifetime_days' => (int) $this->detailData['cookie_lifetime_days'],
                'form_recipients' => $this->detailData['form_recipients'] ?: null,
            ]
        );

        $this->showSuccessIndicator = true;

        $this->dispatch('toast', [
            'title' => __('Saved'),
            'description' => __('The settings have been saved.'),
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
            name="detailData.homepage_page_id"
            label="{{ __('Homepage') }}"
            :options="array_merge(
                [['value' => '', 'label' => '- ' . __('None selected') . ' -']],
                Page::where('collection_id', null)->orderBy('name')->get()->map(fn($p) => ['value' => $p->id, 'label' => $this->formatName($p->name)])->toArray()
            )"
        />
    </div>

    <div class="pt-4">
        <x-noerd::forms.input
            name="detailData.form_recipients"
            label="{{ __('Form Recipients') }}"
        />
        <p class="text-sm text-gray-500 mt-1">{{ __('Recipients of all form submissions. Separate multiple email addresses with commas.') }}</p>
    </div>

    <div class="pt-4">
        <x-noerd::forms.checkbox
            name="detailData.show_cookie_banner"
            label="{{ __('Show Cookie Banner') }}"
            live
        />
        <p class="text-sm text-gray-500 mt-1">{{ __('Required to use Analytics') }}</p>
    </div>

    @if($detailData['show_cookie_banner'] ?? false)
        <div class="pt-4">
            <x-noerd::forms.input-select
                name="detailData.cookie_lifetime_days"
                label="{{ __('Cookie Consent Duration') }}"
                :options="[
                    ['value' => 30, 'label' => __('30 days')],
                    ['value' => 90, 'label' => __('3 months')],
                    ['value' => 182, 'label' => __('6 months (recommended)')],
                    ['value' => 365, 'label' => __('12 months')],
                ]"
            />
            <p class="text-sm text-gray-500 mt-1">{{ __('Applies equally to acceptance and rejection. After this period visitors are asked again.') }}</p>
        </div>

        <div class="pt-4">
            <x-noerd::forms.input
                name="detailData.google_analytics_id"
                label="{{ __('Google Analytics') }}"
            />
            <p class="text-sm text-gray-500 mt-1">{{ __('Google Analytics Measurement ID (e.g. G-XXXXXXXXXX)') }}</p>
        </div>
    @endif

    <x-slot:footer>
        <x-noerd::delete-save-bar showDelete="false"/>
    </x-slot:footer>
</x-noerd::page>


