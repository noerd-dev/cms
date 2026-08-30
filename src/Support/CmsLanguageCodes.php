<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Illuminate\Support\Facades\Schema;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Helpers\TenantHelper;
use Throwable;

/**
 * Resolves the CMS content language codes from the `cms_languages` table, so a
 * tenant can add a language (Danish, Polish, …) purely through the CMS UI —
 * without a code change anywhere in the framework.
 *
 * Both lookups degrade gracefully when the table is unavailable (install or
 * migration time) or empty, so a fresh installation keeps working.
 */
final class CmsLanguageCodes
{
    /**
     * Codes assumed to exist before any language has been configured.
     *
     * @var array<int, string>
     */
    public const FALLBACK = ['de', 'en'];

    /**
     * Baseline for recognising a stored translatable array. Always unioned with
     * the configured codes: content written before a language was removed — or
     * imported from another installation — must still be recognised.
     *
     * @var array<int, string>
     */
    public const BUILT_IN = ['de', 'en', 'fr', 'es', 'it', 'nl'];

    /**
     * Memoized active codes, keyed by tenant id (0 = no tenant context) so a
     * process serving several tenants (Octane, queue workers, tests) never
     * serves one tenant's language list to another.
     *
     * @var array<int, array<int, string>>
     */
    private static array $activeCache = [];

    /** @var array<int, string>|null */
    private static ?array $knownCache = null;

    /**
     * Active codes of the CURRENT tenant, default language first — used to decide
     * which language slots a translatable element field is initialised with.
     *
     * @return array<int, string>
     */
    public static function active(): array
    {
        $tenantId = (int) (TenantHelper::currentTenantId() ?? 0);

        if (isset(self::$activeCache[$tenantId])) {
            return self::$activeCache[$tenantId];
        }

        $codes = self::query(
            fn(): array => CmsLanguage::query()
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('sort_order')
                ->pluck('code')
                ->all(),
        );

        return self::$activeCache[$tenantId] = $codes === [] ? self::FALLBACK : $codes;
    }

    /**
     * Every code configured anywhere, unioned with the built-in baseline. Used as
     * the heuristic for "is this array a language map?" while rendering stored
     * page data — that check must also recognise codes of other tenants and of
     * languages that were deactivated after the content was written.
     *
     * @return array<int, string>
     */
    public static function known(): array
    {
        if (self::$knownCache !== null) {
            return self::$knownCache;
        }

        $configured = self::query(
            fn(): array => CmsLanguage::withoutGlobalScopes()->distinct()->pluck('code')->all(),
        );

        return self::$knownCache = array_values(array_unique([...self::BUILT_IN, ...$configured]));
    }

    /**
     * Whether an array reads as a language map: every key is a known language
     * code. The single heuristic shared by every "is this value translatable?"
     * decision in the module.
     *
     * @param  array<array-key, mixed>  $value
     */
    public static function isLanguageMap(array $value): bool
    {
        $keys = array_keys($value);

        return $keys !== [] && count(array_intersect($keys, self::known())) === count($keys);
    }

    /**
     * Drop the memoized codes — call after adding or removing a language.
     */
    public static function clearCache(): void
    {
        self::$activeCache = [];
        self::$knownCache = null;
    }

    /**
     * @param  callable(): array<int, mixed>  $resolver
     * @return array<int, string>
     */
    private static function query(callable $resolver): array
    {
        try {
            if (! Schema::hasTable('cms_languages')) {
                return [];
            }

            return array_values(array_filter(array_map(
                static fn($code): string => (string) $code,
                $resolver(),
            )));
        } catch (Throwable) {
            return [];
        }
    }
}
