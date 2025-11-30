<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsSetting extends Model
{
    use HasFactory;

    protected $table = 'cms_settings';

    protected $fillable = [
        'tenant_id',
        'homepage_page_id',
        'google_analytics_id',
        'show_cookie_banner',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_cookie_banner' => 'boolean',
        ];
    }

    protected static function newFactory()
    {
        return \Noerd\Cms\Database\Factories\CmsSettingFactory::new();
    }
}
