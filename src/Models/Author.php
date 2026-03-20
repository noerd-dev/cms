<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Database\Factories\AuthorFactory;
use Noerd\Traits\BelongsToTenant;

class Author extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'additional_fields' => 'array',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    protected static function newFactory(): AuthorFactory
    {
        return AuthorFactory::new();
    }
}
