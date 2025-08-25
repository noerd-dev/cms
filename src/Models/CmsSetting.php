<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CmsSetting extends Model
{
    use HasFactory;
    protected $table = 'cms_settings';

    protected $fillable = [
        'tenant_id',
        'homepage_page_id',
    ];

    protected static function newFactory()
    {
        return \Noerd\Cms\Database\Factories\CmsSettingFactory::new();
    }
}
