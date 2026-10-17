<?php

declare(strict_types=1);

namespace Noerd\Cms\Traits;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Services\ContentDocumentService;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Facades\Noerd;
use Noerd\Models\SetupLanguage;

/**
 * The document editor of a detail component whose record extends a content
 * (website page, intranet page, article): running text and elements in ONE
 * editor. Render it with
 *
 *     <x-cms::content-document :config="$this->contentDocumentConfig()" />
 *
 * and call `persistContentDocument($record->content)` at the end of `store()`.
 *
 * The editor writes its state into `$contentDocumentDraft` (untrusted, no round
 * trip); the server merges it into the locked working document
 * (`ContentDocumentService::apply()`) whenever it acts: on save and on a
 * language switch. Every write path checks `canSaveObject()` itself —
 * WriteGuardHook only covers store()/delete().
 */
trait EditsContentDocument
{
    /**
     * The working document (all languages). Never written by the client.
     *
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $contentDocument = [];

    #[Locked]
    public bool $contentDocumentLoaded = false;

    #[Locked]
    public ?string $contentDocumentLanguage = null;

    /**
     * The editor's state in one language: {language, sections}. Untrusted.
     *
     * @var array<string, mixed>
     */
    public array $contentDocumentDraft = [];

    /**
     * The content of the edited record, resolved through the tenant-scoped
     * extension model — null for a new record and for a record of another tenant.
     */
    abstract public function editableContent(): ?Content;

    /**
     * The registry key of the edited record's content type (`cms.page`, …).
     */
    abstract public function contentDocumentType(): string;

    /**
     * The name of a new record is a language map the translatable input binds
     * into (`detailData.name.{code}`) — the browser cannot set a key on null.
     */
    public function renderingEditsContentDocument(): void
    {
        if (array_key_exists('name', $this->detailData) && ! is_array($this->detailData['name'])) {
            $this->detailData['name'] = [SetupLanguage::selectedCode() => (string) ($this->detailData['name'] ?? '')];
        }
    }

    /**
     * Everything the editor needs to start: sections in the selected language,
     * the element catalog of the content type and the definitions of every
     * element the document holds.
     *
     * @return array<string, mixed>
     */
    public function contentDocumentConfig(): array
    {
        $this->ensureContentDocument();
        $service = $this->contentDocumentService();
        $catalog = $service->catalog($this->contentDocumentType());
        $definitions = collect($catalog)->keyBy('key')->all();

        foreach ($this->contentDocument as $section) {
            if ($section['kind'] === 'element' && ! isset($definitions[$section['key']])) {
                $definitions[$section['key']] = $service->definition($section['key']);
            }
        }

        return [
            'sections' => $service->forEditor($this->contentDocument, $this->contentDocumentLanguage),
            'language' => $this->contentDocumentLanguage,
            'catalog' => array_values(array_map(fn(array $entry): array => array_diff_key($entry, ['fields' => true]), $catalog)),
            'definitions' => $definitions,
            'previews' => (object) $service->mediaPreviews($this->contentDocument),
            'collectionCounts' => (object) $service->collectionCounts($this->contentDocument),
            'editable' => $this->canSaveObject(),
        ];
    }

    /**
     * Merge the editor's latest state into the working document.
     */
    public function applyContentDocumentDraft(): void
    {
        $this->ensureContentDocument();
        $draft = $this->contentDocumentDraft;
        $this->contentDocumentDraft = [];

        if (! $this->canSaveObject() || ! is_array($draft['sections'] ?? null)) {
            return;
        }

        $language = is_string($draft['language'] ?? null) && preg_match('/^[a-z]{2,8}(-[A-Za-z]{2,8})?$/', $draft['language'])
            ? $draft['language']
            : $this->contentDocumentLanguage;

        $service = $this->contentDocumentService();
        $this->contentDocument = $service->apply(
            $this->contentDocument,
            $draft['sections'],
            $language,
            $service->allowedKeys($this->contentDocumentType()),
        );
    }

    /**
     * Write the document into the content's rows — the end of `store()`.
     */
    public function persistContentDocument(?Content $content): void
    {
        $this->applyContentDocumentDraft();

        if ($content === null || ! $this->canSaveObject()) {
            return;
        }

        $service = $this->contentDocumentService();
        $this->contentDocument = $service->persist($content, $this->contentDocument);

        // The editor keeps its content (and the cursor); it only learns which
        // elements are saved now — element collections need a saved element.
        $this->dispatch(
            'content-document-saved',
            saved: array_values(array_column(array_filter($this->contentDocument, fn(array $section): bool => $section['kind'] === 'element' && $section['id'] !== null), 'uid')),
            collectionCounts: (object) $service->collectionCounts($this->contentDocument),
        );
    }

    /**
     * Start over from the stored rows — after the edited record changed (a
     * copy was made and is edited now).
     */
    public function reloadContentDocument(): void
    {
        $this->contentDocumentLoaded = false;
        $this->contentDocumentDraft = [];
        $this->dispatchContentDocument();
    }

    #[On('languageChanged')]
    public function contentDocumentLanguageChanged(): void
    {
        $this->applyContentDocumentDraft();
        $this->contentDocumentLanguage = SetupLanguage::selectedCode();
        $this->dispatchContentDocument();
    }

    /**
     * The preview URL of a picked media file (image fields of the elements).
     */
    public function contentDocumentMediaPreview(int $mediaId): ?string
    {
        return app(MediaResolverContract::class)->getPreviewUrl($mediaId);
    }

    /**
     * Open the media library as a picker for an image field; the editor
     * listens for `mediaSelected` with this token.
     */
    public function pickContentDocumentMedia(string $token): void
    {
        $picker = app(MediaResolverContract::class)->pickerComponent();

        if (! $this->canSaveObject() || $picker === null || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $token)) {
            return;
        }

        Noerd::modal($picker, ['selectMode' => true, 'selectContext' => $token, 'selectToken' => $token]);
    }

    /**
     * Open the entries of an element collection field of a SAVED element.
     */
    public function manageContentDocumentCollection(string $uid, string $field): void
    {
        $this->ensureContentDocument();
        $content = $this->editableContent();
        $section = collect($this->contentDocument)->first(fn(array $section): bool => $section['uid'] === $uid && $section['kind'] === 'element');

        if (! $this->canSaveObject() || $content === null || $section === null || $section['id'] === null) {
            return;
        }

        $element = ContentElement::query()->where('content_id', $content->getKey())->find($section['id']);
        $definition = collect(FieldHelper::flattenFields(FieldHelper::getElementFields($section['key'])['fields'] ?? []))
            ->first(fn(array $candidate): bool => preg_replace('/^\w+\./', '', (string) ($candidate['name'] ?? '')) === $field
                && ($candidate['type'] ?? null) === 'element-collection');

        if ($element === null || $definition === null) {
            return;
        }

        $service = app(ElementCollectionService::class);
        $title = (string) (FieldHelper::getElementFields($section['key'])['title'] ?? $section['key']);
        $collection = $service->ensure(
            ElementCollectionService::OWNER_ELEMENT_PAGE,
            (int) $element->id,
            (int) $content->tenant_id,
            $field,
            $definition['fields'] ?? [],
            $service->displayName((string) ($definition['label'] ?? $field), $title),
        );

        Noerd::modal('cms::collection-entries-list', [
            'collectionKey' => $collection->collection_key,
            'elementCollection' => true,
        ]);
    }

    private function ensureContentDocument(): void
    {
        if ($this->contentDocumentLoaded) {
            return;
        }

        $this->contentDocument = $this->contentDocumentService()->load($this->editableContent());
        $this->contentDocumentLanguage ??= SetupLanguage::selectedCode();
        $this->contentDocumentLoaded = true;
    }

    /**
     * Hand the document to the editor again — after a language switch the
     * running texts and the translatable fields change.
     */
    private function dispatchContentDocument(): void
    {
        $this->dispatch('content-document-load', config: $this->contentDocumentConfig());
    }

    private function contentDocumentService(): ContentDocumentService
    {
        return app(ContentDocumentService::class);
    }
}
