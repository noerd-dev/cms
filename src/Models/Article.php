<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Cms\Database\Factories\ArticleFactory;
use Noerd\Traits\BelongsToTenant;

class Article extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'title' => 'array',
        'slug' => 'array',
        'is_active' => 'boolean',
        'publication_date' => 'date',
        'custom_attributes' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('publication_date')
            ->whereDate('publication_date', '<=', now()->toDateString());
    }

    protected static function newFactory(): ArticleFactory
    {
        return ArticleFactory::new();
    }
}
