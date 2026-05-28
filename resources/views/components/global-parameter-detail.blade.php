<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    #[Url(as: 'globalParameterId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = GlobalParameter::class;

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
        $this->validate([
            'detailData.key' => ['required', 'string', 'max:255'],
            'detailData.value' => ['required'],
        ]);

        $data = $this->detailData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;
        $data['is_translatable'] = (bool) ($data['is_translatable'] ?? false);

        $value = $this->detailData['value'];

        if ($data['is_translatable']) {
            $data['value'] = json_encode(is_array($value) ? $value : [$this->defaultLanguageCode() => (string) $value]);
        } else {
            if (is_array($value)) {
                $value = $this->normalizeValueForEditing($value, false);
            }
            $data['value'] = json_encode((string) $value);
        }

        $globalParameter = GlobalParameter::updateOrCreate(['id' => $this->modelId], $data);

        $this->dispatch('storeElements');
        $this->storeProcess($globalParameter);
    }

    public function delete(): void
    {
        $globalParameter = GlobalParameter::find($this->modelId);
        $globalParameter->delete();
        $this->closeModalProcess($this->getListComponent());
    }

    #[On('languageChanged')]
    public function refresh()
    {
        $this->dispatch('$refresh');
    }

    private function injectValueField(): void
    {
        $fields = array_values(array_filter(
            $this->pageLayout['fields'] ?? [],
            fn ($field) => ($field['name'] ?? null) !== 'detailData.value',
        ));

        $fields[] = [
            'name' => 'detailData.value',
            'label' => 'Value',
            'type' => $this->detailData['is_translatable'] ? 'translatableText' : 'text',
            'colspan' => 12,
        ];

        $this->pageLayout['fields'] = $fields;
    }

    private function normalizeValueForEditing(mixed $value, bool $isTranslatable): mixed
    {
        if ($isTranslatable) {
            $languageCodes = $this->activeTenantLanguageCodes();
            $defaultCode = $this->defaultLanguageCode();

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
            $code = $this->defaultLanguageCode();
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
        return CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->pluck('code')
            ->all();
    }

    private function defaultLanguageCode(): string
    {
        return session('selectedLanguage')
            ?? CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
                ->where('is_default', true)
                ->value('code')
            ?? 'de';
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ __('Global Parameter') }}

            <div class="ml-auto" :class="isModal ? 'mr-22' : ''">
                <div class="flex bg-white p-1 rounded-lg w-fit border border-gray-200">
                    <livewire:cms::language-switcher/>
                </div>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false && isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
