<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdList;

    public $listModel = Article::class;

    public ?string $detailRoute = 'cms.article.detail';

    public $detailComponent = 'cms::article-detail';

    public function mount(): void
    {
        $this->mountList();
        $this->ensureDefaultLanguage();

        if (empty($this->listFilters['language'])) {
            $this->listFilters['language'] = session('selectedLanguage');
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
        $selectedLanguage = $this->listFilters['language']
            ?? session('selectedLanguage');

        $rows = $this->listQuery($this->listModel)
            ->with('author')
            ->paginate($this->perPage);

        foreach ($rows->items() as $row) {
            if (is_array($row->title)) {
                $row->title = $row->title[$selectedLanguage] ?? array_values($row->title)[0] ?? '';
            }
            $row->author_name = $row->author?->name ?? '';
        }

        return $this->buildList($rows);
    }
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
