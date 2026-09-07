<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Database\Factories\PageFactory;
use Noerd\Cms\Services\FieldTypeConverter;
use Noerd\Traits\BelongsToTenant;
use Noerd\Traits\GuardedByObjectPermission;

class Page extends Model
{
    use BelongsToTenant;
    use GuardedByObjectPermission;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'cms_pages';

    protected $attributes = [
        'is_active' => true,
    ];

    public function elements(): HasMany
    {
        return $this->hasMany(ElementPage::class)->orderBy('sort');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }

    protected static function booted(): void
    {
        // Apply field type conversion before saving collection pages
        static::saving(function (self $page): void {
            if ($page->collection_id && $page->collection) {
                $collectionKey = mb_strtolower($page->collection->collection_key);

                // Apply field type conversion to ensure data format consistency
                if ($page->data && is_array($page->data)) {
                    $page->data = FieldTypeConverter::convertCollectionData($page->data, $collectionKey);
                }
            }
        });

        // Remove element collections owned by this entry or by any of its page
        // elements. Their row pages cascade away via the pages.collection_id FK.
        static::deleting(function (self $page): void {
            $elementPageIds = $page->elements()->pluck('id')->all();

            Collection::query()
                ->where('is_element_collection', true)
                ->where(function ($query) use ($page, $elementPageIds): void {
                    $query->where('page_id', $page->id)
                        ->orWhereIn('element_page_id', $elementPageIds);
                })
                ->get()
                ->each
                ->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta_noindex' => 'boolean',
            'is_active' => 'boolean',
            'data' => 'array',
            'name' => 'array',
            'slug' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
            'custom_attributes' => 'array',
        ];
    }
}
