<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\CmsSettingFactory;
use Noerd\Contracts\TenantSingletonContract;
use Noerd\Traits\TenantSingleton;

/**
 * The CMS settings of a tenant: ONE row per tenant (unique `tenant_id`), read
 * through CmsSetting::forTenant() (TenantSingleton) and edited on the settings
 * page (cms::cms-settings-page).
 */
class CmsSetting extends Model implements TenantSingletonContract
{
    use HasFactory;
    use TenantSingleton;

    public const DEFAULT_COOKIE_LIFETIME_DAYS = 182;

    /**
     * A Google tag ID the website may load: a Tag Manager container (`GTM-…`)
     * or a Google tag / Analytics measurement ID (`G-…`). The strict pattern
     * also keeps the value safe inside the JavaScript that loads it.
     */
    public const GOOGLE_TAG_PATTERN = '/^(GTM-[A-Z0-9]{4,12}|G-[A-Z0-9]{4,15})$/';

    protected $table = 'cms_settings';

    protected $guarded = [];

    /**
     * The values a tenant without a settings row behaves with.
     *
     * @return array<string, mixed>
     */
    public static function tenantDefaults(): array
    {
        return ['show_cookie_banner' => false];
    }

    /**
     * Parsed list of form notification recipients for a tenant.
     *
     * @return list<string>
     */
    public static function formRecipientsForTenant(int $tenantId): array
    {
        $setting = self::forTenant($tenantId);

        if (! is_string($setting->form_recipients) || $setting->form_recipients === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $setting->form_recipients))));
    }

    /**
     * Storage duration of the cookie consent in days. Falls back to the cookie-consent
     * package configuration when installed, then to the built-in default — the config
     * key does not exist on installations without the banner package.
     *
     * The same value is used for acceptance and rejection: re-asking users who declined
     * sooner than users who accepted is a deceptive design pattern.
     */
    public function cookieLifetimeInDays(): int
    {
        return $this->cookie_lifetime_days
            ?: ((int) config('laravel-cookie-consent.cookie_lifetime') ?: self::DEFAULT_COOKIE_LIFETIME_DAYS);
    }

    protected static function newFactory(): CmsSettingFactory
    {
        return CmsSettingFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'homepage_page_id' => 'integer',
            'show_cookie_banner' => 'boolean',
            'cookie_lifetime_days' => 'integer',
        ];
    }
}
