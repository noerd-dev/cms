<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Services\PageSlugService;
use Noerd\Cms\Traits\HandlesImageFields;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Helpers\TenantHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use HandlesImageFields;
    use LanguageFilterTrait;
    use NoerdDetail;
    use WithFileUploads;

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

        $this->validateFromLayout();
        $this->validate([
            'detailData.title' => ['required', 'array'],
        ]);

        // Only what the form declares — never the id or the tenant from the
        // client state.
        $data = $this->writableDetailData(Article::class);

        // Slugs are normalized and unique across every language of the tenant
        // (the blog resolves a slug in any of them); one naming another host
        // is refused. Articles live under /blog, so the site's reserved
        // prefixes do not apply.
        $prepared = app(PageSlugService::class)->prepare(
            is_array($this->detailData['slug'] ?? null) ? $this->detailData['slug'] : [],
            (int) TenantHelper::currentTenantId(),
            $this->modelId ? (int) $this->modelId : null,
            Article::class,
            checkReserved: false,
        );

        foreach ($prepared['errors'] as $language => $message) {
            $this->addError('detailData.slug.' . $language, $message);
        }

        if ($prepared['errors'] !== []) {
            return;
        }

        $data['slug'] = $prepared['slugs'];

        if ($this->modelId) {
            $article = Article::find($this->modelId);

            // An id the tenant scope cannot resolve stores nothing — never a new article.
            if (! $article instanceof Article) {
                return;
            }

            $article->fill($data)->save();
        } else {
            $article = Article::create($data);
        }

        $this->finishStore($article);
    }

    /**
     * @return list<string>
     */
    protected function imageFieldNames(): array
    {
        return $this->imageFieldNamesOf($this->pageLayout['fields'] ?? []);
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
