<?php

declare(strict_types=1);

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Models\Content;

class ElementPage extends Model
{
    protected $table = 'content_elements';

    protected $guarded = [];

    public function content()
    {
        return $this->belongsTo(Content::class);
    }
}
