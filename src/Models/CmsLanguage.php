<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;
use Noerd\Traits\HasListScopes;

class CmsLanguage extends Model
{
    use BelongsToTenant;
    use HasListScopes;

    protected $table = 'cms_languages';

    protected $guarded = [];

    protected array $searchable = [
        'name',
        'code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Create default English language for a tenant if none exists
     */
    public static function ensureDefaultLanguageForTenant(int $tenantId): self
    {
        $existing = static::where('tenant_id', $tenantId)->first();

        if ($existing) {
            return $existing;
        }

        return static::create([
            'tenant_id' => $tenantId,
            'code' => 'en',
            'name' => 'English',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
        ]);
    }

    protected static function boot(): void
    {
        parent::boot();

        // After deleting, ensure there's still a default language
        static::deleted(function (CmsLanguage $language): void {
            if ($language->is_default) {
                $newDefault = static::where('tenant_id', $language->tenant_id)
                    ->where('is_active', true)
                    ->first();

                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }
        });

        // Before saving, ensure is_default consistency
        static::saving(function (CmsLanguage $language): void {
            // If this is the first language for the tenant, make it default
            if (! $language->exists) {
                $existingCount = static::where('tenant_id', $language->tenant_id)->count();
                if ($existingCount === 0) {
                    $language->is_default = true;
                    $language->is_active = true;
                }
            }
        });

        // After saving, ensure only one default per tenant
        static::saved(function (CmsLanguage $language): void {
            if ($language->is_default) {
                static::where('tenant_id', $language->tenant_id)
                    ->where('id', '!=', $language->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // If no default exists after save, set one
            $hasDefault = static::where('tenant_id', $language->tenant_id)
                ->where('is_default', true)
                ->exists();

            if (! $hasDefault) {
                $firstActive = static::where('tenant_id', $language->tenant_id)
                    ->where('is_active', true)
                    ->first();

                if ($firstActive) {
                    $firstActive->update(['is_default' => true]);
                }
            }
        });
    }
}
