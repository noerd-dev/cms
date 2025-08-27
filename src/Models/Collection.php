<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\CollectionFactory;

class Collection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'collections';

    public function rows()
    {
        return $this->hasMany(Page::class, 'collection_id')->orderBy('sort');
    }


    protected static function newFactory()
    {
        return CollectionFactory::new();
    }
}
