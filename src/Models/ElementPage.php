<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElementPage extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'element_page';

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
