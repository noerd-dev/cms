<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdList;

    public function mount(): void
    {
        $this->listId = Str::random();
        $this->loadListFilters();
        $this->ensureDefaultLanguage();
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
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('cms::article-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        $selectedLanguage = $this->listFilters['language']
            ?? session('selectedLanguage');

        $rows = $this->listQuery(Article::class)
            ->with('author')
            ->paginate($this->perPage);

        foreach ($rows->items() as $row) {
            if (is_array($row->title)) {
                $row->title = $row->title[$selectedLanguage] ?? array_values($row->title)[0] ?? '';
            }
            $row->author_name = $row->author?->name ?? '';
        }

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        $this->loadListFilters();

        if (empty($this->listFilters['language'])) {
            $this->listFilters['language'] = session('selectedLanguage');
        }

        if ((int) request()->articleId) {
            $this->listAction(request()->articleId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
