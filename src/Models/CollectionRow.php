<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionRow extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'collection_rows';

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    protected static function newFactory()
    {
        return \Noerd\Cms\Database\Factories\CollectionRowFactory::new();
    }
}
