<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\CmsLanguageFactory;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Scopes\TenantScope;
use Noerd\Traits\BelongsToTenant;

class CmsLanguage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'cms_languages';

    protected $guarded = [];

    /**
     * Create default English language for a tenant if none exists
     */
    public static function ensureDefaultLanguageForTenant(int $tenantId): self
    {
        // Explicitly tenant-keyed: this also runs for a tenant OTHER than the
        // acting user's (Tenant::created hook, install command), where the
        // global tenant scope would filter the lookup down to nothing.
        $existing = static::forTenant($tenantId)->first();

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

    /**
     * Query the languages of ONE tenant regardless of the acting user's tenant.
     */
    public static function forTenant(int $tenantId): Builder
    {
        return static::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId);
    }

    protected static function newFactory(): CmsLanguageFactory
    {
        return CmsLanguageFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();

        // Adding, changing or removing a language changes which codes count as
        // translatable — drop the memoized lists.
        static::saved(fn() => CmsLanguageCodes::clearCache());
        static::deleted(fn() => CmsLanguageCodes::clearCache());

        // After deleting, ensure there's still a default language
        static::deleted(function (CmsLanguage $language): void {
            if ($language->is_default) {
                $newDefault = static::forTenant((int) $language->tenant_id)
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
                $existingCount = static::forTenant((int) $language->tenant_id)->count();
                if ($existingCount === 0) {
                    $language->is_default = true;
                    $language->is_active = true;
                }
            }
        });

        // After saving, ensure only one default per tenant
        static::saved(function (CmsLanguage $language): void {
            if ($language->is_default) {
                static::forTenant((int) $language->tenant_id)
                    ->where('id', '!=', $language->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // If no default exists after save, set one
            $hasDefault = static::forTenant((int) $language->tenant_id)
                ->where('is_default', true)
                ->exists();

            if (! $hasDefault) {
                $firstActive = static::forTenant((int) $language->tenant_id)
                    ->where('is_active', true)
                    ->first();

                if ($firstActive) {
                    $firstActive->update(['is_default' => true]);
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
