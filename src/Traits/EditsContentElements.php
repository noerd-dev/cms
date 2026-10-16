<?php

declare(strict_types=1);

namespace Noerd\Cms\Traits;

use Livewire\Attributes\On;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Services\ContentElementEditorService;
use Noerd\Models\SetupLanguage;

/**
 * The element builder of a detail component whose record extends a content
 * (website page, intranet page, article). Render it with
 *
 *     @include('cms::partials.content-elements', ['content' => $this->editableContent()])
 *
 * Every mutation is guarded like store(): WriteGuardHook only covers
 * store()/delete(), so these check the write permission themselves.
 */
trait EditsContentElements
{
    public $lastChangeTime;

    /**
     * The content of the edited record, resolved through the tenant-scoped
     * extension model — null for a new record and for a record of another tenant.
     */
    abstract public function editableContent(): ?Content;

    /**
     * The name of a new record is a language map the translatable input binds
     * into (`detailData.name.{code}`) — the browser cannot set a key on null.
     */
    public function renderingEditsContentElements(): void
    {
        if (array_key_exists('name', $this->detailData) && ! is_array($this->detailData['name'])) {
            $this->detailData['name'] = [SetupLanguage::selectedCode() => (string) ($this->detailData['name'] ?? '')];
        }
    }

    #[On('elementPicked')]
    public function addElement($elementKey, $token = 'insert-end'): void
    {
        $content = $this->editableContent();

        if (! $this->canSaveObject() || ! $content) {
            return;
        }

        $position = str_starts_with((string) $token, 'insert-at-') ? (int) mb_substr((string) $token, 10) : null;

        $this->contentElementEditor()->addElement($content, (string) $elementKey, $position);
        $this->lastChangeTime = time();
    }

    public function elementSort($elementId, $newPosition): void
    {
        $content = $this->editableContent();

        if (! $this->canSaveObject() || ! $content) {
            return;
        }

        $this->contentElementEditor()->move($content, (int) $elementId, (int) $newPosition);
        $this->lastChangeTime = time();
    }

    public function duplicateElement(int $elementId): void
    {
        $content = $this->editableContent();

        if (! $this->canSaveObject() || ! $content) {
            return;
        }

        if ($this->contentElementEditor()->duplicate($content, $elementId)) {
            $this->lastChangeTime = time();
            $this->dispatch('reloadPageComponent');
        }
    }

    public function deleteElement(int $elementId): void
    {
        $content = $this->editableContent();

        if (! $this->canSaveObject() || ! $content) {
            return;
        }

        if ($this->contentElementEditor()->delete($content, $elementId)) {
            $this->lastChangeTime = time();
            $this->dispatch('reloadPageComponent');
        }
    }

    #[On('reloadPageComponent')]
    public function reloadPage(): void
    {
        $this->lastChangeTime = time();
    }

    private function contentElementEditor(): ContentElementEditorService
    {
        return app(ContentElementEditorService::class);
    }
}
