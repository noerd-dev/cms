<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Services\PageSlugService;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Helpers\TenantHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use LanguageFilterTrait;
    use NoerdDetail;

    public ?string $detailPrimary = 'articleId';

    public $detailModel = Article::class;

    public function generateSlug(string $name, ?string $languageCode = null): string
    {
        return app(PageSlugService::class)->generate($name, $languageCode, $this->defaultLanguageCode());
    }

    public function updated($propertyName, $value): void
    {
        if (! str_starts_with($propertyName, 'detailData.title.')) {
            return;
        }

        $language = str_replace('detailData.title.', '', $propertyName);

        if (! empty($value)) {
            if (! isset($this->detailData['slug']) || ! is_array($this->detailData['slug'])) {
                $this->detailData['slug'] = array_fill_keys($this->activeLanguageCodes(), '');
            }

            if (empty($this->detailData['slug'][$language] ?? '')) {
                $this->detailData['slug'][$language] = $this->generateSlug($value, $language);
            }
        }
    }

    #[On('authorSelected')]
    public function authorSelected($authorId): void
    {
        $author = Author::find($authorId);
        if (! $author) {
            return;
        }

        $this->detailData['author_id'] = $author->id;
        $this->relationTitles['author_id'] = $author->name;
    }

    #[On('languageChanged')]
    public function onLanguageChanged(): void
    {
        // The roundtrip re-renders the translatable inputs against the new language.
    }

    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        $this->validate([
            'detailData.title' => ['required', 'array'],
        ]);

        $data = collect($this->detailData)
            ->except(['created_at', 'updated_at'])
            ->toArray();
        $data['tenant_id'] = TenantHelper::currentTenantId();

        // Clean empty slug values
        $cleanSlugData = [];
        if (isset($this->detailData['slug']) && is_array($this->detailData['slug'])) {
            foreach ($this->detailData['slug'] as $lang => $slug) {
                if (! empty($slug)) {
                    $cleanSlugData[$lang] = $slug;
                }
            }
        }
        $data['slug'] = $cleanSlugData;
        $data['title'] = $this->detailData['title'];

        $article = Article::updateOrCreate(
            ['id' => $this->modelId],
            $data,
        );

        $this->storeProcess($article);
    }

    /**
     * Title and slug are translatable: every active language gets its key.
     */
    protected function detailLoaded(Model $article): void
    {
        foreach (['title', 'slug'] as $field) {
            $values = is_array($this->detailData[$field] ?? null) ? $this->detailData[$field] : [];

            foreach ($this->activeLanguageCodes() as $lang) {
                $values[$lang] ??= '';
            }

            $this->detailData[$field] = $values;
        }
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ __('Article') }}

            <div class="ml-auto">
                <livewire:cms::language-switcher />
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)" />
    </x-slot:footer>
</x-noerd::page>
