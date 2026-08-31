<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;

class ElementPage extends Model
{
    protected $table = 'element_page';

    protected $guarded = [];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
