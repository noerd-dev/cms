<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\PageFactory;
use Noerd\Cms\Services\FieldTypeConverter;

class Page extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta_noindex' => 'boolean',
        'data' => 'array',
        'name' => 'array',
        'slug' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function elements()
    {
        return $this->hasMany(ElementPage::class)->orderBy('sort');
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    protected static function newFactory()
    {
        return PageFactory::new();
    }

    /**
     * Boot method to add model event listeners
     */
    protected static function boot(): void
    {
        parent::boot();

        // Apply field type conversion before saving collection pages
        static::saving(function ($page): void {
            if ($page->collection_id && $page->collection) {
                $collectionKey = mb_strtolower($page->collection->collection_key);

                // Apply field type conversion to ensure data format consistency
                if ($page->data && is_array($page->data)) {
                    $page->data = FieldTypeConverter::convertCollectionData($page->data, $collectionKey);
                }
            }
        });
    }
}
