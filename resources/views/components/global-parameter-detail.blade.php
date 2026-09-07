<?php

use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Helpers\TenantHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use LanguageFilterTrait;
    use NoerdDetail;

    public ?string $detailPrimary = 'globalParameterId';

    public $detailModel = GlobalParameter::class;

    public function mount(): void
    {
        $this->initDetail();

        $globalParameter = new GlobalParameter;
        if ($this->modelId) {
            $globalParameter = GlobalParameter::find($this->modelId) ?? new GlobalParameter;
        }

        $this->detailData = $globalParameter->toArray();
        $this->detailData['is_translatable'] = (bool) ($this->detailData['is_translatable'] ?? false);

        if (isset($this->detailData['value']) && is_string($this->detailData['value'])) {
            $decoded = json_decode($this->detailData['value'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->detailData['value'] = $decoded;
            }
        }

        $this->detailData['value'] = $this->normalizeValueForEditing(
            $this->detailData['value'] ?? null,
            $this->detailData['is_translatable'],
        );

        $this->injectValueField();
    }

    public function updatedDetailDataIsTranslatable($value): void
    {
        $isTranslatable = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->detailData['is_translatable'] = $isTranslatable;

        $this->detailData['value'] = $this->normalizeValueForEditing(
            $this->detailData['value'] ?? null,
            $isTranslatable,
        );

        $this->injectValueField();
    }

    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        $this->validate([
            'detailData.key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cms_global_parameters', 'key')
                    ->where('tenant_id', TenantHelper::currentTenantId())
                    ->ignore($this->modelId),
            ],
            'detailData.value' => ['required'],
        ]);

        $data = collect($this->detailData)
            ->except(['created_at', 'updated_at'])
            ->toArray();
        $data['tenant_id'] = TenantHelper::currentTenantId();
        $data['is_translatable'] = (bool) ($data['is_translatable'] ?? false);

        $value = $this->detailData['value'];

        if ($data['is_translatable']) {
            $data['value'] = json_encode(is_array($value) ? $value : [$this->selectedLanguageCode() => (string) $value]);
        } else {
            if (is_array($value)) {
                $value = $this->normalizeValueForEditing($value, false);
            }
            $data['value'] = json_encode((string) $value);
        }

        $globalParameter = GlobalParameter::updateOrCreate(['id' => $this->modelId], $data);

        $this->storeProcess($globalParameter);
    }

    #[On('languageChanged')]
    public function onLanguageChanged(): void
    {
        // The roundtrip re-renders the translatable inputs against the new language.
    }

    /**
     * The value field is declared in the YAML as a plain text field; whether it
     * renders as a translatable input depends on the record's own flag, which
     * no static configuration can express — so only its type is switched here.
     */
    private function injectValueField(): void
    {
        foreach ($this->pageLayout['fields'] ?? [] as $index => $field) {
            if (($field['name'] ?? null) === 'detailData.value') {
                $this->pageLayout['fields'][$index]['type'] = $this->detailData['is_translatable'] ? 'translatableText' : 'text';
            }
        }
    }

    private function normalizeValueForEditing(mixed $value, bool $isTranslatable): mixed
    {
        if ($isTranslatable) {
            $languageCodes = $this->activeTenantLanguageCodes();
            $defaultCode = $this->selectedLanguageCode();

            if (! is_array($value)) {
                $scalar = is_scalar($value) ? (string) $value : '';
                $normalized = array_fill_keys($languageCodes ?: [$defaultCode], '');
                $normalized[$defaultCode] = $scalar;

                return $normalized;
            }

            foreach ($languageCodes as $code) {
                if (! array_key_exists($code, $value)) {
                    $value[$code] = '';
                }
            }

            return $value;
        }

        if (is_array($value)) {
            $code = $this->selectedLanguageCode();
            if (isset($value[$code]) && $value[$code] !== '') {
                return (string) $value[$code];
            }

            foreach ($value as $entry) {
                if ($entry !== '' && $entry !== null) {
                    return (string) $entry;
                }
            }

            return '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function activeTenantLanguageCodes(): array
    {
        return $this->activeLanguageCodes();
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ __('Global Parameter') }}

            <div class="ml-auto">
                <livewire:cms::language-switcher/>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
