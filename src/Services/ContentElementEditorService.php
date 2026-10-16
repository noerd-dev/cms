<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Illuminate\Database\Eloquent\Builder;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;

/**
 * The write side of the element builder: adding, ordering, duplicating and
 * deleting the elements of a content — of any type (website page, intranet
 * page, article).
 *
 * Every method takes the OWNING content (resolved by the caller through the
 * tenant-scoped extension record) and only ever touches elements of that
 * content — an element id from another content is ignored, never acted upon.
 */
final class ContentElementEditorService
{
    /**
     * Append the element (position null) or insert it at the given position,
     * shifting the following elements down.
     */
    public function addElement(Content $content, string $elementKey, ?int $position = null): ContentElement
    {
        if ($position !== null) {
            $this->elementsOf($content)->where('sort', '>=', $position)->increment('sort');
            $sort = $position;
        } else {
            $sort = (int) ($this->elementsOf($content)->max('sort') ?? 0) + 1;
        }

        return ContentElement::create([
            'content_id' => $content->getKey(),
            'element_key' => $elementKey,
            'sort' => $sort,
            'data' => [],
        ]);
    }

    /**
     * Re-sequence the content's elements with the given element at the new
     * position (the contract of Livewire's wire:sort).
     */
    public function move(Content $content, int $elementId, int $newPosition): void
    {
        $elements = $this->elementsOf($content)->orderBy('sort')->get();
        $loop = 0;

        foreach ($elements as $element) {
            if ($newPosition === $loop) {
                $loop++;
            }

            if ($element->id === $elementId) {
                $element->sort = $newPosition;
            } else {
                $element->sort = $loop++;
            }

            $element->save();
        }
    }

    /**
     * Insert a copy of the element directly after the original. Returns null
     * when the element does not belong to the content.
     */
    public function duplicate(Content $content, int $elementId): ?ContentElement
    {
        $element = $this->elementsOf($content)->find($elementId);

        if (! $element) {
            return null;
        }

        $this->elementsOf($content)->where('sort', '>', $element->sort)->increment('sort');

        return ContentElement::create([
            'content_id' => $content->getKey(),
            'element_key' => $element->element_key,
            'sort' => $element->sort + 1,
            'data' => $element->data,
        ]);
    }

    /**
     * Delete the element (its element collections cascade through the model
     * hook). Returns false when the element does not belong to the content.
     */
    public function delete(Content $content, int $elementId): bool
    {
        $element = $this->elementsOf($content)->find($elementId);

        if (! $element) {
            return false;
        }

        $element->delete();

        return true;
    }

    /**
     * Append copies of every element of $from to $to, keeping the order.
     */
    public function copyElements(Content $from, Content $to): void
    {
        foreach ($this->elementsOf($from)->orderBy('sort')->get() as $element) {
            ContentElement::create([
                'content_id' => $to->getKey(),
                'element_key' => $element->element_key,
                'sort' => $element->sort,
                'data' => $element->data,
            ]);
        }
    }

    private function elementsOf(Content $content): Builder
    {
        return ContentElement::query()->where('content_id', $content->getKey());
    }
}
