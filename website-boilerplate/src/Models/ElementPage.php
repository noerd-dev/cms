<?php

declare(strict_types=1);

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;

class ElementPage extends Model
{
    protected $table = 'cms_page_elements';

    protected $guarded = [];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
