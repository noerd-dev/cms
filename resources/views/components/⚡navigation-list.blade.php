<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdList;

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

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'navigation-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with(): array
    {
        $rows = Navigation::paginate(self::PAGINATION);

        $selectedLanguage = $this->activeListFilters['language']
            ?? session('selectedLanguage')
            ?? $this->getDefaultLanguageCode();

        // decode name json for table output per selected language
        foreach ($rows as $row) {
            $oldName = $row->name;
            $decoded = is_string($row->name) ? json_decode($row->name, true) : ($row->name ?? []);
            $row->name = $decoded[$selectedLanguage] ?? array_values($decoded)[0] ?? $oldName;
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

        if ((int) request()->navigationId) {
            $this->listAction(request()->navigationId);
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
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
