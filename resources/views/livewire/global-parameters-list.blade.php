<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Noerd\Helpers\StaticConfigHelper;
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

    public function storeActiveTableFilters(): void
    {
        session(['activeTableFilters' => $this->activeTableFilters]);

        if (! empty($this->activeTableFilters['language'])) {
            session(['selectedLanguage' => $this->activeTableFilters['language']]);
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'global-parameter-detail',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with()
    {
        $rows = GlobalParameter::where('tenant_id', Auth::user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('value', 'like', '%'.$this->search.'%')
                        ->orWhere('key', 'like', '%'.$this->search.'%');
                });
            })
            ->paginate(self::PAGINATION);

        $selectedLanguage = $this->activeTableFilters['language']
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

        $tableConfig = StaticConfigHelper::getTableConfig('global-parameters-list');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }

    public function rendering()
    {
        $this->loadActiveTableFilters();

        $selectedLanguage = session('selectedLanguage');
        if ($selectedLanguage && empty($this->activeTableFilters['language'])) {
            $this->activeTableFilters['language'] = $selectedLanguage;
        }

        if (empty($this->activeTableFilters['language']) && empty(session('selectedLanguage'))) {
            $defaultCode = $this->getDefaultLanguageCode();
            $this->activeTableFilters['language'] = $defaultCode;
            session(['selectedLanguage' => $defaultCode]);
        }

        if ((int) request()->globalParameterId) {
            $this->tableAction(request()->globalParameterId);
        }

        if (request()->create) {
            $this->tableAction();
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
    <div>
        @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
    </div>
</x-noerd::page>
