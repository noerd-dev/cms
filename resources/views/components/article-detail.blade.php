<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;

    public ?string $detailPrimary = 'articleId';

    public $detailModel = Article::class;

    public function mount(): void
    {
        $this->initDetail();

        $article = new Article;
        if ($this->modelId) {
            $article = Article::find($this->modelId) ?? new Article;
        }

        $this->detailData = $article->toArray();

        $activeLangCodes = $this->getActiveTenantLanguageCodes();
        if (empty($activeLangCodes)) {
            $activeLangCodes = [$this->getDefaultLanguageCode()];
        }

        // Initialize title as translatable array
        if (! isset($this->detailData['title']) || ! is_array($this->detailData['title'])) {
            $this->detailData['title'] = array_fill_keys($activeLangCodes, '');
        } else {
            foreach ($activeLangCodes as $lang) {
                if (! isset($this->detailData['title'][$lang])) {
                    $this->detailData['title'][$lang] = '';
                }
            }
        }

        // Initialize slug as translatable array
        if (! isset($this->detailData['slug']) || ! is_array($this->detailData['slug'])) {
            $this->detailData['slug'] = array_fill_keys($activeLangCodes, '');
        } else {
            foreach ($activeLangCodes as $lang) {
                if (! isset($this->detailData['slug'][$lang])) {
                    $this->detailData['slug'][$lang] = '';
                }
            }
        }

        // Load author relation title
        if (! empty($this->detailData['author_id'])) {
            $author = Author::find($this->detailData['author_id']);
            if ($author) {
                $this->relationTitles['author_id'] = $author->name;
            }
        }
    }

    public function getDefaultLanguageCode(): string
    {
        $defaultLanguage = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_default', true)
            ->first();

        return $defaultLanguage?->code ?? 'en';
    }

    public function getActiveTenantLanguageCodes(): array
    {
        return CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->pluck('code')
            ->toArray();
    }

    public function generateSlug(string $name, ?string $languageCode = null): string
    {
        $slug = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $name);
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = mb_trim($slug, '-');

        if ($languageCode && $languageCode !== $this->getDefaultLanguageCode()) {
            $slug = $languageCode . '/' . $slug;
        }

        return '/' . $slug;
    }

    public function updated($propertyName, $value): void
    {
        if (! str_starts_with($propertyName, 'detailData.title.')) {
            return;
        }

        $language = str_replace('detailData.title.', '', $propertyName);

        if (! empty($value)) {
            if (! isset($this->detailData['slug']) || ! is_array($this->detailData['slug'])) {
                $this->detailData['slug'] = array_fill_keys($this->getActiveTenantLanguageCodes(), '');
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
    public function refresh(): void
    {
        $this->dispatch('$refresh');
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
        $data['tenant_id'] = auth()->user()->selected_tenant_id;

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
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ __('Article') }}

            <div class="ml-auto">
                <div class="flex items-center gap-4">
                    <livewire:cms::language-switcher />
                </div>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)" />
    </x-slot:footer>
</x-noerd::page>
