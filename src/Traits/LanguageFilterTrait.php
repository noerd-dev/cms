<?php

declare(strict_types=1);

namespace Noerd\Cms\Traits;

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\ListFilter;

trait LanguageFilterTrait
{
    protected function ensureDefaultLanguage(): string
    {
        $tenantId = TenantHelper::currentTenantId();
        $currentLanguage = session('selectedLanguage');

        // Check if the current session language exists and is active for this tenant
        if ($currentLanguage) {
            $languageExists = CmsLanguage::where('tenant_id', $tenantId)
                ->where('code', $currentLanguage)
                ->where('is_active', true)
                ->exists();

            if ($languageExists) {
                return $currentLanguage;
            }
        }

        // Get the default language for this tenant
        $defaultLanguage = CmsLanguage::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        $code = $defaultLanguage?->code ?? $this->defaultLanguageCode();
        session(['selectedLanguage' => $code]);

        return $code;
    }

    /**
     * The tenant's default language code (first active code).
     */
    protected function defaultLanguageCode(): string
    {
        return CmsLanguageCodes::active()[0] ?? CmsLanguageCodes::FALLBACK[0];
    }

    /**
     * The language the editor currently works in: the session selection when
     * it is one of the tenant's active codes, otherwise the default language.
     */
    protected function selectedLanguageCode(): string
    {
        $selected = session('selectedLanguage');

        return is_string($selected) && in_array($selected, $this->activeLanguageCodes(), true)
            ? $selected
            : $this->defaultLanguageCode();
    }

    /**
     * Active language codes of the tenant, default language first.
     *
     * @return array<int, string>
     */
    protected function activeLanguageCodes(): array
    {
        return CmsLanguageCodes::active();
    }

    protected function hasMultipleLanguages(): bool
    {
        return CmsLanguage::where('tenant_id', TenantHelper::currentTenantId())
            ->where('is_active', true)
            ->count() > 1;
    }

    /**
     * The language dropdown of a list: one option per active language of the
     * tenant, the default language first. No CMS table has a `language` column —
     * the language picks which translation a list shows, so the filter is
     * manual: the list reads `$this->listFilters['language']` itself.
     */
    protected function getLanguageListFilter(): ListFilter
    {
        return ListFilter::options(__('Language'), 'language', fn(): array => CmsLanguage::where('tenant_id', TenantHelper::currentTenantId())
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->pluck('name', 'code')
            ->all())->manual();
    }
}
