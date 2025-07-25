<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Model;

class Element extends Model
{
    public function pages(): void
    {
        $this->belongsToMany(Page::class);
    }
}
