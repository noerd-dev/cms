<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Database\Factories\NavigationFactory;
use Noerd\Traits\BelongsToTenant;

class Navigation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'cms_navigations';

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    protected static function newFactory(): NavigationFactory
    {
        return NavigationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'new_tab' => 'boolean',
        ];
    }

    protected function navigationKey(): Attribute
    {
        return Attribute::make(
            set: fn(string $value) => mb_strtoupper($value),
        );
    }
}
