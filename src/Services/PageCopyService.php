<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Illuminate\Support\Facades\DB;
use Noerd\Cms\Models\Page;

/**
 * Copies a website page together with a content of its own and the elements.
 */
final class PageCopyService
{
    public function __construct(private readonly PageSlugService $slugs) {}

    /**
     * Names get a " 2" suffix, slugs are uniquified per language, a collection
     * entry's data title is suffixed too.
     */
    public function copy(Page $source): Page
    {
        return DB::transaction(function () use ($source): Page {
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

            // The replica gets a content of its own with copies of the
            // elements (ExtendsContent).
            $copy = $source->replicate(['id']);
            $copy->name = $newName;
            $copy->slug = $newSlug;

            if ($source->collection_id && is_array($copy->data)) {
                $copy->data = $this->suffixDataTitle($copy->data);
            }

            $copy->save();

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
}
