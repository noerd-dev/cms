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
        'form_recipients',
    ];

    /**
     * Parsed list of form notification recipients for a tenant.
     *
     * @return list<string>
     */
    public static function formRecipientsForTenant(int $tenantId): array
    {
        $setting = self::query()->where('tenant_id', $tenantId)->first();

        if (! $setting || ! is_string($setting->form_recipients) || $setting->form_recipients === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $setting->form_recipients))));
    }

    protected static function newFactory()
    {
        return \Noerd\Cms\Database\Factories\CmsSettingFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_cookie_banner' => 'boolean',
        ];
    }
}
