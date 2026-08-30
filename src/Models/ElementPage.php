<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElementPage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'element_page';

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    protected static function booted(): void
    {
        // Remove element collections owned by this element instance. Their row
        // pages cascade away via the pages.collection_id foreign key.
        static::deleting(function (self $elementPage): void {
            Collection::query()
                ->where('is_element_collection', true)
                ->where('element_page_id', $elementPage->id)
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
            'data' => 'array',
        ];
    }
}
