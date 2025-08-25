<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Model;

class CmsSetting extends Model
{
    protected $table = 'cms_settings';

    protected $fillable = [
        'tenant_id',
        'homepage_page_id',
    ];
}
