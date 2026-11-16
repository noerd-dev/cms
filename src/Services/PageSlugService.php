<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\CmsLanguageCodes;

/**
 * Slug generation for pages and articles: one place that decides how a title
 * becomes a URL, what shape a stored slug has and how a slug is kept unique.
 *
 * A stored slug is a site path: a leading `/`, lower case, no empty segment
 * (`//`), no trailing slash (except the root `/`), no scheme, no query and no
 * fragment — the same shape Redirect::normalizePath() gives a request path,
 * so a slug, a redirect source and a visitor's URL compare as equals.
 *
 * The website resolves a slug across ALL languages of the tenant, so a slug
 * is unique across every language and every record of the tenant, and it may
 * not start with a prefix the application's own routes use (RESERVED_PREFIXES
 * plus `cms.reserved_slug_prefixes`).
 */
final class PageSlugService
{
    /**
     * Path prefixes the website must never serve a page under: the backend,
     * the APIs, Livewire, the asset and media routes, the homepage alias and
     * the website's own blog routes. A
     * project adds its own through `cms.reserved_slug_prefixes`.
     *
     * @var list<string>
     */
    public const RESERVED_PREFIXES = [
        '/api',
        '/blog',
        '/cms',
        '/cms-assets',
        '/index',
        '/livewire',
        '/login',
        '/logout',
        '/media',
        '/noerd',
        '/noerd-apps',
        '/noerd-modal',
        '/oauth',
        '/storage',
        '/up',
        '/vendor',
    ];

    /**
     * '/ueber-uns' for the default language, '/en/ueber-uns' for every other
     * language. The transliteration language is fixed to German so umlauts
     * fold to ae/oe/ue/ss regardless of the content language.
     */
    public function generate(string $name, ?string $languageCode, string $defaultLanguageCode): string
    {
        $slug = Str::slug($name, '-', 'de');

        if ($languageCode && $languageCode !== $defaultLanguageCode) {
            $slug = $languageCode . '/' . $slug;
        }

        return '/' . $slug;
    }

    /**
     * Bring a slug an editor typed into the stored shape: query and fragment
     * cut, backslashes read as slashes, lower case, whitespace and characters
     * a path cannot carry turned into `-`, empty segments removed, a leading
     * `/`, no trailing slash. Letters of every script survive (`/über-uns`).
     */
    public function normalize(string $slug): string
    {
        $slug = preg_split('/[?#]/', mb_trim($slug))[0] ?? '';
        $slug = mb_strtolower(str_replace('\\', '/', $slug));

        $segments = [];
        foreach (explode('/', $slug) as $segment) {
            $segment = (string) preg_replace('/[^\p{L}\p{N}._~-]+/u', '-', $segment);
            $segment = mb_trim($segment, '-');

            if ($segment !== '' && $segment !== '.' && $segment !== '..') {
                $segments[] = $segment;
            }
        }

        return '/' . implode('/', $segments);
    }

    /**
     * Why a slug an editor typed cannot be stored, or null when it can. A slug
     * naming another host (`//evil.com`, `https://…`) would turn a redirect
     * into an open redirect; a reserved prefix would shadow an application
     * route.
     */
    public function violation(string $slug): ?string
    {
        $raw = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $slug);
        $raw = str_replace('\\', '/', $raw);

        if (str_starts_with($raw, '//') || preg_match('/^[a-z][a-z0-9+.\-]*:/i', $raw)) {
            return __('The URL must be a path on this website (e.g. /about-us).');
        }

        $prefix = $this->reservedPrefixOf($this->normalize($slug));

        if ($prefix !== null) {
            return __('The URL may not start with :prefix — the path is used by the application.', ['prefix' => $prefix]);
        }

        return null;
    }

    /**
     * The reserved prefix a normalized slug starts with, or null.
     */
    public function reservedPrefixOf(string $normalizedSlug): ?string
    {
        foreach ($this->reservedPrefixes() as $prefix) {
            if ($normalizedSlug === $prefix || str_starts_with($normalizedSlug, $prefix . '/')) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function reservedPrefixes(): array
    {
        $extra = array_map(
            fn($prefix): string => '/' . mb_trim(mb_strtolower((string) $prefix), '/'),
            array_filter((array) config('cms.reserved_slug_prefixes', []), 'is_scalar'),
        );

        return array_values(array_unique(array_filter([...self::RESERVED_PREFIXES, ...$extra], fn(string $prefix): bool => $prefix !== '/')));
    }

    /**
     * Append -2, -3, … until no other record of the tenant uses the slug in
     * ANY of the tenant's languages — the website resolves a slug across all of
     * them. The record's own slugs in its other languages do not count.
     *
     * @param  class-string<Model>  $model
     */
    public function ensureUnique(string $slug, string $languageCode, int $tenantId, ?int $excludeId = null, string $model = Page::class): string
    {
        // The code is interpolated into a JSON path and originates from a
        // Livewire property name — only known language codes are accepted.
        if (! in_array($languageCode, CmsLanguageCodes::known(), true)) {
            throw new InvalidArgumentException("Unknown language code [{$languageCode}].");
        }

        $codes = $this->languageCodesFor($tenantId, $languageCode);
        $originalSlug = $slug;
        $counter = 2;

        while (true) {
            $query = $model::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($codes, $slug): void {
                    foreach ($codes as $code) {
                        $query->orWhere("slug->{$code}", $slug);
                    }
                });

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (! $query->exists()) {
                return $slug;
            }

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
    }

    /**
     * generate() + ensureUnique() in one step.
     */
    public function uniqueFor(string $name, string $languageCode, string $defaultLanguageCode, int $tenantId, ?int $excludeId = null): string
    {
        return $this->ensureUnique($this->generate($name, $languageCode, $defaultLanguageCode), $languageCode, $tenantId, $excludeId);
    }

    /**
     * The slugs of one record as the editor typed them, normalized and made
     * unique per language. Empty slugs are dropped; a slug that cannot be
     * stored is reported by language (violation()) and left out.
     *
     * @param  array<array-key, mixed>  $slugs
     * @param  class-string<Model>  $model
     * @param  bool  $checkReserved  pages live at the site root; articles under /blog
     * @return array{slugs: array<string, string>, errors: array<string, string>}
     */
    public function prepare(array $slugs, int $tenantId, ?int $excludeId = null, string $model = Page::class, bool $checkReserved = true): array
    {
        $prepared = [];
        $errors = [];

        foreach ($slugs as $language => $slug) {
            if (! is_string($language) || ! is_scalar($slug) || mb_trim((string) $slug) === '') {
                continue;
            }

            if (! in_array($language, CmsLanguageCodes::known(), true)) {
                continue;
            }

            $violation = $checkReserved
                ? $this->violation((string) $slug)
                : $this->hostViolation((string) $slug);

            if ($violation !== null) {
                $errors[$language] = $violation;

                continue;
            }

            $normalized = $this->normalize((string) $slug);

            // Two languages of one record may share a slug; other records may not.
            $prepared[$language] = in_array($normalized, $prepared, true)
                ? $normalized
                : $this->ensureUnique($normalized, $language, $tenantId, $excludeId, $model);
        }

        return ['slugs' => $prepared, 'errors' => $errors];
    }

    private function hostViolation(string $slug): ?string
    {
        $raw = str_replace('\\', '/', (string) preg_replace('/[\x00-\x20\x7F]+/', '', $slug));

        return str_starts_with($raw, '//') || preg_match('/^[a-z][a-z0-9+.\-]*:/i', $raw)
            ? __('The URL must be a path on this website (e.g. /about-us).')
            : null;
    }

    /**
     * The tenant's active language codes plus the given one, restricted to
     * codes that are safe inside a JSON path.
     *
     * @return list<string>
     */
    private function languageCodesFor(int $tenantId, string $languageCode): array
    {
        $codes = CmsLanguage::query()
            ->ofTenant($tenantId)
            ->where('is_active', true)
            ->pluck('code')
            ->map(fn($code): string => (string) $code)
            ->all();

        $codes = array_values(array_unique([$languageCode, ...$codes]));

        return array_values(array_filter($codes, fn(string $code): bool => (bool) preg_match('/^[a-z]{2,8}(-[a-z0-9]{2,8})?$/i', $code)));
    }
}
