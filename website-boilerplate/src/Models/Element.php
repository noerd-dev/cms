<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;

class Element extends Model
{
    protected $table = 'elements';

    protected $guarded = [];

    public function elementPages()
    {
        return $this->hasMany(ElementPage::class);
    }

    public function pages()
    {
        return $this->belongsToMany(Page::class);
    }
}
