<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdList;

    #[Computed]
    public function tableFilters(): array
    {
        if (! $this->hasMultipleLanguages()) {
            return [];
        }

        return [$this->getLanguageListFilter()];
    }

    public function storeActiveListFilters(): void
    {
        session(['listFilters' => $this->listFilters]);

        if (! empty($this->listFilters['language'])) {
            session(['selectedLanguage' => $this->listFilters['language']]);
        }
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('cms::global-parameter-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        $rows = $this->listQuery(GlobalParameter::class)->paginate($this->perPage);

        $selectedLanguage = $this->listFilters['language']
            ?? session('selectedLanguage')
            ?? $this->getDefaultLanguageCode();

        foreach ($rows as $row) {
            $raw = $row->value;
            $decoded = json_decode($raw, true);

            if ($row->is_translatable && is_array($decoded)) {
                $row->value = $decoded[$selectedLanguage] ?? array_values($decoded)[0] ?? '';
            } elseif (is_string($decoded) || is_numeric($decoded)) {
                $row->value = (string) $decoded;
            } elseif (is_array($decoded)) {
                $row->value = $decoded[$selectedLanguage] ?? array_values($decoded)[0] ?? '';
            } else {
                $row->value = (string) $raw;
            }
        }

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        $this->loadListFilters();

        $selectedLanguage = session('selectedLanguage');
        if ($selectedLanguage && empty($this->listFilters['language'])) {
            $this->listFilters['language'] = $selectedLanguage;
        }

        if (empty($this->listFilters['language']) && empty(session('selectedLanguage'))) {
            $defaultCode = $this->getDefaultLanguageCode();
            $this->listFilters['language'] = $defaultCode;
            session(['selectedLanguage' => $defaultCode]);
        }

        if ((int) request()->globalParameterId) {
            $this->listAction(request()->globalParameterId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }

    private function getDefaultLanguageCode(): string
    {
        $defaultLanguage = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_default', true)
            ->first();

        return $defaultLanguage?->code ?? 'de';
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
