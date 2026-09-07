<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Illuminate\Support\Facades\DB;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;

/**
 * The write side of the page builder: adding, ordering, duplicating and
 * deleting the elements of a page, and copying a page with its elements.
 *
 * Every method takes the OWNING page (resolved through the tenant scope by
 * the caller) and only ever touches elements of that page — an element id
 * from another page is ignored, never acted upon.
 */
final class PageElementEditorService
{
    public function __construct(private readonly PageSlugService $slugs) {}

    /**
     * Append the element (position null) or insert it at the given position,
     * shifting the following elements down.
     */
    public function addElement(Page $page, string $elementKey, ?int $position = null): ElementPage
    {
        if ($position !== null) {
            $this->elementsOf($page)->where('sort', '>=', $position)->increment('sort');
            $sort = $position;
        } else {
            $sort = (int) ($this->elementsOf($page)->max('sort') ?? 0) + 1;
        }

        return ElementPage::create([
            'page_id' => $page->getKey(),
            'element_key' => $elementKey,
            'sort' => $sort,
            'data' => [],
        ]);
    }

    /**
     * Re-sequence the page's elements with the given element at the new
     * position (the contract of Livewire's wire:sort).
     */
    public function move(Page $page, int $elementPageId, int $newPosition): void
    {
        $elements = $this->elementsOf($page)->orderBy('sort')->get();
        $loop = 0;

        foreach ($elements as $element) {
            if ($newPosition === $loop) {
                $loop++;
            }

            if ($element->id === $elementPageId) {
                $element->sort = $newPosition;
            } else {
                $element->sort = $loop++;
            }

            $element->save();
        }
    }

    /**
     * Insert a copy of the element directly after the original. Returns null
     * when the element does not belong to the page.
     */
    public function duplicate(Page $page, int $elementPageId): ?ElementPage
    {
        $element = $this->elementsOf($page)->find($elementPageId);

        if (! $element) {
            return null;
        }

        $this->elementsOf($page)->where('sort', '>', $element->sort)->increment('sort');

        return ElementPage::create([
            'page_id' => $page->getKey(),
            'element_key' => $element->element_key,
            'sort' => $element->sort + 1,
            'data' => $element->data,
        ]);
    }

    /**
     * Delete the element (its element collections cascade through the model
     * hook). Returns false when the element does not belong to the page.
     */
    public function delete(Page $page, int $elementPageId): bool
    {
        $element = $this->elementsOf($page)->find($elementPageId);

        if (! $element) {
            return false;
        }

        $element->delete();

        return true;
    }

    /**
     * Copy a page with its elements: names get a " 2" suffix, slugs are
     * uniquified per language, a collection entry's data title is suffixed too.
     */
    public function copyPage(Page $source): Page
    {
        return DB::transaction(function () use ($source): Page {
            $source->loadMissing('elements');

            $newName = [];
            foreach ($source->name ?? [] as $language => $name) {
                $newName[$language] = ! empty($name) ? $name . ' 2' : $name;
            }

            $newSlug = [];
            foreach ($source->slug ?? [] as $language => $slug) {
                if (! empty($slug)) {
                    $newSlug[$language] = $this->slugs->ensureUnique($slug . '-2', (string) $language, (int) $source->tenant_id);
                }
            }

            $copy = $source->replicate(['id']);
            $copy->name = $newName;
            $copy->slug = $newSlug;

            if ($source->collection_id && is_array($copy->data)) {
                $copy->data = $this->suffixDataTitle($copy->data);
            }

            $copy->save();

            foreach ($source->elements as $element) {
                ElementPage::create([
                    'page_id' => $copy->id,
                    'element_key' => $element->element_key,
                    'sort' => $element->sort,
                    'data' => $element->data,
                ]);
            }

            return $copy;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function suffixDataTitle(array $data): array
    {
        if (! isset($data['title'])) {
            return $data;
        }

        if (is_array($data['title'])) {
            foreach ($data['title'] as $language => $value) {
                if (! empty($value)) {
                    $data['title'][$language] = $value . ' 2';
                }
            }
        } elseif (is_string($data['title']) && $data['title'] !== '') {
            $data['title'] .= ' 2';
        }

        return $data;
    }

    private function elementsOf(Page $page): \Illuminate\Database\Eloquent\Builder
    {
        return ElementPage::query()->where('page_id', $page->getKey());
    }
}
