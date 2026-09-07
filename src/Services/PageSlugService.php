<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\CmsLanguageCodes;

/**
 * Slug generation for pages and articles: one place that decides how a title
 * becomes a URL and how a slug is kept unique per language and tenant.
 */
final class PageSlugService
{
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
     * Append -2, -3, … until no other record of the tenant uses the slug in
     * that language.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public function ensureUnique(string $slug, string $languageCode, int $tenantId, ?int $excludeId = null, string $model = Page::class): string
    {
        // The code is interpolated into a JSON path and originates from a
        // Livewire property name — only known language codes are accepted.
        if (! in_array($languageCode, CmsLanguageCodes::known(), true)) {
            throw new InvalidArgumentException("Unknown language code [{$languageCode}].");
        }

        $originalSlug = $slug;
        $counter = 2;

        while (true) {
            $query = $model::query()
                ->where('tenant_id', $tenantId)
                ->where("slug->{$languageCode}", $slug);

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
}
