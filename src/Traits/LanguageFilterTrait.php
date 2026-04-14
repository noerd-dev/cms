<?php

namespace Noerd\Cms\Traits;

use Noerd\Cms\Models\CmsLanguage;

trait LanguageFilterTrait
{
    protected function ensureDefaultLanguage(): string
    {
        $tenantId = auth()->user()->selected_tenant_id;
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

        $code = $defaultLanguage?->code ?? 'de';
        session(['selectedLanguage' => $code]);

        return $code;
    }

    protected function hasMultipleLanguages(): bool
    {
        return CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_active', true)
            ->count() > 1;
    }

    protected function getLanguageListFilter(): array
    {
        $filter['label'] = __('Language');
        $filter['column'] = 'language';
        $filter['type'] = 'Picklist';
        $filter['options'] = [];

        $languages = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        foreach ($languages as $language) {
            $filter['options'][$language->code] = $language->name;
        }

        return $filter;
    }
}
