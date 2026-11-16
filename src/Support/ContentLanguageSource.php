<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Noerd\Models\SetupLanguage;

/**
 * Which languages the document of a content type is written in — declared per
 * type when it is registered (ContentTypeRegistry::register(languageSource:)):
 *
 * - `Cms`: the website languages of the tenant's CMS (`cms_languages`,
 *   CmsLanguageCodes) — website pages and everything the website renders
 * - `Setup`: the languages the backend is run in (`setup_languages`,
 *   SetupLanguage; without rows the core's defaults `en`, `de`) — content of
 *   a module that is no website (an intranet page, an article of a portal)
 *
 * The document editor offers, accepts and stores only the codes of its type's
 * source; the language it opens in is the session selection when it is one of
 * them, else the first (the default) code.
 */
enum ContentLanguageSource: string
{
    case Cms = 'cms';
    case Setup = 'setup';

    /**
     * The active codes of the current tenant, the default language first.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        if ($this === self::Cms) {
            return array_values(CmsLanguageCodes::active());
        }

        $codes = array_values(array_map('strval', SetupLanguage::activeCodes()));

        return $codes !== [] ? $codes : ['en', 'de'];
    }

    /**
     * The language the editor works in: the session selection when the source
     * offers it, otherwise the default language.
     */
    public function selectedCode(): string
    {
        $codes = $this->codes();
        $selected = session(SetupLanguage::SESSION_KEY);

        return is_string($selected) && in_array($selected, $codes, true) ? $selected : ($codes[0] ?? 'en');
    }

    /**
     * The default language of a tenant for headless callers.
     */
    public function defaultFor(int $tenantId): string
    {
        if ($this === self::Cms) {
            return CmsLanguageCodes::defaultFor($tenantId);
        }

        $code = SetupLanguage::query()->ofTenant($tenantId)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->value('code');

        return is_string($code) && $code !== '' ? $code : 'en';
    }
}
