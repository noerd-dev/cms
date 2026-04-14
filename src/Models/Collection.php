<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Database\Factories\CollectionFactory;
use Noerd\Models\NoerdUser;

class Collection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'collections';

    public function rows(): HasMany
    {
        return $this->hasMany(Page::class, 'collection_id')->orderBy('sort');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'created_by');
    }

    protected static function newFactory()
    {
        return CollectionFactory::new();
    }

    protected function collectionKey(): Attribute
    {
        return Attribute::make(
            set: fn(string $value) => mb_strtoupper($value),
        );
    }
}
