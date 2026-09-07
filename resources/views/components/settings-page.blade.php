<?php

use Illuminate\Validation\Rule;
use Livewire\Component;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Helpers\TenantHelper;
use Noerd\Traits\NoerdSettingsPage;

new class extends Component {
    use NoerdSettingsPage;

    public array $settingsModels = [
        'detailData' => CmsSetting::class,
    ];

    public function store(): void
    {
        $this->validate([
            'detailData.homepage_page_id' => [
                'nullable',
                Rule::exists('cms_pages', 'id')->where('tenant_id', TenantHelper::getSelectedTenantId()),
            ],
            'detailData.google_analytics_id' => ['nullable', 'string', 'max:50'],
            'detailData.show_cookie_banner' => ['boolean'],
            'detailData.cookie_lifetime_days' => ['required', 'integer', 'min:1', 'max:365'],
            'detailData.form_recipients' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail(__(':email is not a valid email address.', ['email' => $email]));
                    }
                }
            }],
        ]);

        $this->detailData['homepage_page_id'] = ($this->detailData['homepage_page_id'] ?? null) ?: null;
        $this->detailData['form_recipients'] = ($this->detailData['form_recipients'] ?? null) ?: null;
        $this->detailData['cookie_lifetime_days'] = (int) ($this->detailData['cookie_lifetime_days'] ?? 0);

        $this->validateFromLayout();

        $this->persistSettings();

        $this->showSuccessIndicator = true;

        $this->dispatch('toast', [
            'title' => __('Saved'),
            'description' => __('The settings have been saved.'),
        ]);
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Settings') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId"/>

    <x-slot:footer>
        <x-noerd::delete-save-bar :show-delete="false"/>
    </x-slot:footer>
</x-noerd::page>
