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
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'navigation-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with(): array
    {
        $rows = Navigation::orderBy('parent_id')->orderBy('sort_order')->paginate(self::PAGINATION);

        $selectedLanguage = $this->listFilters['language']
            ?? session('selectedLanguage')
            ?? $this->getDefaultLanguageCode();

        // Collect parent names for display
        $parentIds = $rows->pluck('parent_id')->filter()->unique()->toArray();
        $parents = $parentIds ? Navigation::whereIn('id', $parentIds)->get()->keyBy('id') : collect();

        // decode name json for table output per selected language
        foreach ($rows as $row) {
            $oldName = $row->name;
            $decoded = is_string($row->name) ? json_decode($row->name, true) : ($row->name ?? []);
            $displayName = $decoded[$selectedLanguage] ?? array_values($decoded)[0] ?? $oldName;

            if ($row->parent_id && $parents->has($row->parent_id)) {
                $parentDecoded = is_string($parents[$row->parent_id]->name)
                    ? json_decode($parents[$row->parent_id]->name, true)
                    : ($parents[$row->parent_id]->name ?? []);
                $parentName = $parentDecoded[$selectedLanguage] ?? (is_array($parentDecoded) ? array_values($parentDecoded)[0] ?? '' : '');
                $displayName = '↳ ' . $displayName;
                $row->parent_name = $parentName;
            }

            $row->name = $displayName;
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
