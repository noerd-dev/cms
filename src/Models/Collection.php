<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Database\Factories\CollectionFactory;
use Noerd\Models\NoerdUser;
use Noerd\Traits\BelongsToTenant;

class Collection extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'cms_collections';

    public function rows(): HasMany
    {
        return $this->hasMany(Page::class, 'collection_id')->orderBy('sort');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'created_by');
    }

    /**
     * The collection entry (page) that owns this element collection.
     */
    public function ownerPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    protected static function booted(): void
    {
        // The rows would cascade away through the cms_pages.collection_id
        // foreign key — past the model hooks, leaving their contents, elements
        // and element collections behind. Delete them through the models.
        static::deleting(function (self $collection): void {
            // Every row, past the read guard too: a deletion leaves none behind.
            Page::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $collection->tenant_id)
                ->where('collection_id', $collection->getKey())
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
            'is_element_collection' => 'boolean',
            'element_fields' => 'array',
        ];
    }

    protected function collectionKey(): Attribute
    {
        return Attribute::make(
            set: fn(string $value) => mb_strtoupper($value),
        );
    }
}
