<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'collections';

    public function rows()
    {
        return $this->hasMany(CollectionRow::class, 'collection_id');
    }

    public function pages()
    {
        return $this->belongsToMany(Page::class, 'page_collection')
            ->withPivot('sort_order', 'tenant_id')
            ->withTimestamps()
            ->orderBy('page_collection.sort_order');
    }

    protected static function newFactory()
    {
        return \Noerd\Cms\Database\Factories\CollectionFactory::new();
    }
}
