<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Noerd\Traits\Noerd;

new class extends Component
{
    use LanguageFilterTrait;
    use Noerd;

    public const COMPONENT = 'global-parameters-list';

    protected const ALLOWED_TABLE_FILTERS = ['language'];

    #[Computed]
    public function tableFilters(): array
    {
        if (! $this->hasMultipleLanguages()) {
            return [];
        }

        return [$this->getLanguageFilter()];
    }

    public function storeActiveListFilters(): void
    {
        session(['activeListFilters' => $this->activeListFilters]);

        if (! empty($this->activeListFilters['language'])) {
            session(['selectedLanguage' => $this->activeListFilters['language']]);
        }
    }

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'global-parameter-detail',
            source: self::COMPONENT,
            arguments: ['globalParameterId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with()
    {
        $rows = GlobalParameter::paginate(self::PAGINATION);

        $selectedLanguage = $this->activeListFilters['language']
            ?? session('selectedLanguage')
            ?? $this->getDefaultLanguageCode();

        foreach ($rows as $row) {
            $oldName = $row->value;
            $row->value = json_decode($row->value, true);
            if (is_array($row->value)) {
                $row->value = $row->value[$selectedLanguage] ?? array_values($row->value)[0] ?? $oldName;
            } else {
                $row->value = $oldName;
            }
        }

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        $this->loadActiveListFilters();

        $selectedLanguage = session('selectedLanguage');
        if ($selectedLanguage && empty($this->activeListFilters['language'])) {
            $this->activeListFilters['language'] = $selectedLanguage;
        }

        if (empty($this->activeListFilters['language']) && empty(session('selectedLanguage'))) {
            $defaultCode = $this->getDefaultLanguageCode();
            $this->activeListFilters['language'] = $defaultCode;
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
