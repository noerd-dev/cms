<?php

declare(strict_types=1);

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Support\RichTextValues;

class Collection extends Model
{
    protected $table = 'cms_collections';

    protected $guarded = [];

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    /**
     * The ACTIVE entries of this collection, in their sort order, localized
     * for the selected language, rich text sanitized. The read model carries no tenant scope: the
     * entries are bound to the collection's own tenant explicitly.
     *
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        $pages = $this->hasMany(Page::class)
            ->where('tenant_id', $this->tenant_id)
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $language = \Noerd\Website\Support\WebsiteLanguage::current();
        $fields = CollectionHelper::getCollectionFields(mb_strtolower((string) $this->collection_key))['fields'] ?? [];
        $entries = [];

        foreach ($pages as $page) {
            $data = is_array($page->data) ? $page->data : (json_decode((string) $page->data, true) ?: []);
            // Rich text is printed raw by templates: sanitized, whatever wrote it.
            $data = RichTextValues::sanitize(is_array($data) ? $data : [], $fields);
            $transformedValue = [];
            $transformedValue['id'] = $page->id;
            foreach ($data as $key => $value) {
                $transformedValue[$key] = is_array($value) ? ($value[$language] ?? null) : $value;
            }
            $entries[] = $transformedValue;
        }

        return $entries;
    }
}
