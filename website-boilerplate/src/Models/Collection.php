<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collections';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function pages()
    {
        return $this->hasMany(Page::class);
    }
}
