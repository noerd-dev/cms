<?php

namespace Noerd\Cms\Models;

use Noerd\Models\NoerdUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Noerd\Cms\Database\Factories\CollectionFactory;

class Collection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function collectionKey(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtoupper($value),
        );
    }

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
}
