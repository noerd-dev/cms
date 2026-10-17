<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Illuminate\Database\Eloquent\Builder;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;

/**
 * Element rows of a content that are copied as a whole — a replicated record
 * (ExtendsContent) gets copies of the original's elements. Editing the
 * elements is the document editor's business (ContentDocumentService).
 */
final class ContentElementEditorService
{
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
