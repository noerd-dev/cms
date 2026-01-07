<?php

namespace Noerd\Cms\Traits;

use Noerd\Cms\Models\CmsLanguage;

trait LanguageFilterTrait
{
    protected function ensureDefaultLanguage(): string
    {
        if (empty(session('selectedLanguage'))) {
            $defaultLanguage = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
                ->where('is_default', true)
                ->first();
            $code = $defaultLanguage?->code ?? 'de';
            session(['selectedLanguage' => $code]);

            return $code;
        }

        return session('selectedLanguage');
    }

    protected function hasMultipleLanguages(): bool
    {
        return CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_active', true)
            ->count() > 1;
    }

    protected function getLanguageFilter(): array
    {
        $filter['label'] = __('cms_label_language');
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
