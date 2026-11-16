<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Noerd\Cms\Database\Factories\ArticleFactory;
use Noerd\Traits\BelongsToTenant;

class Article extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'cms_articles';

    protected $attributes = [
        'is_active' => true,
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * Active articles whose publication date has come. "Today" is the date in
     * the application's timezone (noerd keeps no timezone per tenant) unless
     * the caller names it (`Y-m-d`). A plain comparison on the date column
     * ("before tomorrow" — a stored time part never hides today's article), so
     * the index (tenant_id, is_active, publication_date) is used — never
     * DATE(column).
     */
    public function scopePublished(Builder $query, ?string $today = null): Builder
    {
        $today = $today !== null ? Carbon::parse($today) : now(config('app.timezone'));

        return $query->where($this->qualifyColumn('is_active'), true)
            ->whereNotNull($this->qualifyColumn('publication_date'))
            ->where($this->qualifyColumn('publication_date'), '<', $today->copy()->addDay()->toDateString());
    }

    protected static function newFactory(): ArticleFactory
    {
        return ArticleFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'slug' => 'array',
            'is_active' => 'boolean',
            'publication_date' => 'date',
            'custom_attributes' => 'array',
        ];
    }
}
