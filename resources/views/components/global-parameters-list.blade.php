<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdList;

    public $listModel = GlobalParameter::class;

    public ?string $detailRoute = 'cms.global-parameter.detail';

    public $detailComponent = 'cms::global-parameter-detail';

    public function mount(): void
    {
        $this->mountList();

        if (empty($this->listFilters['language'])) {
            $this->listFilters['language'] = session('selectedLanguage') ?: $this->defaultLanguageCode();
        }

        if (empty(session('selectedLanguage'))) {
            session(['selectedLanguage' => $this->listFilters['language']]);
        }
    }

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

        $this->resetPage();
    }

    public function listData(): array
    {
        $rows = $this->listQuery($this->listModel)->paginate($this->perPage);

        $selectedLanguage = $this->listFilters['language']
            ?? session('selectedLanguage')
            ?? $this->defaultLanguageCode();

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

        return $this->buildList($rows);
    }

} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
