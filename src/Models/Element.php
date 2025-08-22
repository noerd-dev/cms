<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Model;

class Element extends Model
{
    protected $guarded = [];

    public function pages()
    {
        return $this->belongsToMany(Page::class);
    }

    public function elementPages()
    {
        return $this->hasMany(ElementPage::class);
    }
}
