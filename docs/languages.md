# Multi-Language Support

The CMS supports multiple languages per tenant. Each translatable field (page names, slugs, article titles, navigation names) stores a value for each active language.

## File Locations

YAML Configurations:
```
app-configs/cms/details/language-detail.yml
app-configs/cms/lists/languages-list.yml
```

Livewire Components:
```
app-modules/cms/resources/views/components/language-detail.blade.php
app-modules/cms/resources/views/components/languages-list.blade.php
app-modules/cms/resources/views/components/language-switcher.blade.php
```

## Managing Languages

Navigate to `/cms/languages` to manage languages. Each language has:

| Field | Type | Description |
|-------|------|-------------|
| `code` | text | ISO language code (e.g., `de`, `en`, `fr`) |
| `name` | text | Display name (e.g., `Deutsch`, `English`) |
| `is_active` | checkbox | Whether the language is available |
| `is_default` | checkbox | Default language for the tenant |
| `sort_order` | number | Display order in language selectors |

## Default Language

Each tenant has exactly one default language. The CMS enforces this automatically:

- When the CMS app is assigned to a tenant (`TenantAppAssigned`), English (`en`) is created as its default language — only for a tenant that has no language yet; a tenant without the CMS gets none
- Setting a new default language automatically unsets the previous default
- If the current default is deactivated, the next active language becomes default
- When the default language is deleted, the next active language is promoted to default

This logic is handled in the `CmsLanguage` model's boot method and the `ensureDefaultLanguageForTenant()` static method.

## Language Switcher

The `language-switcher` component provides a dropdown for selecting the active editing language. It appears in list and detail views that contain translatable fields.

The current language selection is stored in the session (the backend's `selectedLanguage`) and persists across page navigation.

The website keeps the VISITOR's language under a key of its own (`website.language`,
`Noerd\Website\Support\WebsiteLanguage`): a visit of the website never changes the language a
signed-in user works in, and the other way round.

## Languages of a Content Type

The document editor of a content (pages, and the contents other modules register) writes only the
languages of the content type's LANGUAGE SOURCE, declared when the type is registered
(`ContentTypeRegistry::register(languageSource:)`, `Noerd\Cms\Support\ContentLanguageSource`):

| Source | Languages | Used by |
|--------|-----------|---------|
| `Cms` (default) | the tenant's active website languages (`cms_languages`, `CmsLanguageCodes`) | website pages — everything the website renders |
| `Setup` | the backend's active setup languages (`setup_languages`; without rows the core's `en`, `de`) | content of a module that is no website (e.g. UKI's intranet pages and articles) |

The editor opens in the session language when the source offers it, otherwise in the source's default
language; a draft in another language is refused. A legacy scalar value is filed under the source's
default language of the tenant.

## How Translatable Fields Work

Translatable fields store their data as JSON objects with language codes as keys:

```json
{
  "de": "Über uns",
  "en": "About Us",
  "fr": "À propos"
}
```

In YAML configurations, translatable field types are:

| Type | Description |
|------|-------------|
| `translatableText` | Text input per language |
| `translatableRichText` | Rich text editor per language |
| `translatableTextarea` | Textarea per language |

When editing a translatable field, the language switcher controls which language value is displayed in the form.

## Slug Generation

Slugs for non-default languages include a language prefix:

| Language | Default | Example Slug |
|----------|---------|-------------|
| English | Yes | `/about-us` |
| German | No | `/de/ueber-uns` |
| French | No | `/fr/a-propos` |

The slug is auto-generated from the translatable name/title field.

Every slug — generated or typed — is stored in one shape (`PageSlugService::normalize()`): a leading
`/`, lower case, no empty segment, no trailing slash, no query or fragment. The website resolves a
slug in ANY language of the tenant, so a slug is unique across all of them (`-2`, `-3`, … is
appended). A slug naming another host (`//evil.com`, `https://…`) is refused, and a page slug may not
start with a path the application uses (`/api`, `/blog`, `/cms`, `/livewire`, `/noerd`, `/storage`,
… — extend the list with `cms.reserved_slug_prefixes`). `php artisan cms:check-slugs` lists
stored slugs that break these rules without changing them.

## LanguageFilterTrait

The `LanguageFilterTrait` provides language-aware functionality for Livewire components:

```php
use Noerd\Cms\Traits\LanguageFilterTrait;
```

| Method | Description |
|--------|-------------|
| `ensureDefaultLanguage()` | Returns the current session language, falling back to the tenant's default |
| `hasMultipleLanguages()` | Returns `true` if the tenant has more than one active language |
| `getLanguageListFilter()` | Returns a filter config with language picklist options for list views |

The trait stores the selected language in the session. If the session language becomes inactive, it automatically falls back to the tenant's default language.

## CmsLanguage Model

The `CmsLanguage` model (`cms_languages` table):

| Column | Description |
|--------|-------------|
| `code` | ISO language code |
| `name` | Display name |
| `is_active` | Whether the language is enabled (boolean) |
| `is_default` | Whether this is the default language (boolean) |
| `sort_order` | Display ordering |
| `tenant_id` | Tenant scope |

## Supported Language Codes

Without any configured website language (a tenant without the CMS, console code without a tenant) the codes fall back to `en`, `de` (`CmsLanguageCodes::FALLBACK`) — `en` first, the language a CMS tenant is created with.

The system recognizes these language codes for translatable array detection: `de`, `en`, `fr`, `es`, `it`, `nl` — plus every code a tenant configured (`CmsLanguageCodes::known()`). Queries for another tenant's languages use the core scopes: `CmsLanguage::query()->ofTenant($id)` (the former `CmsLanguage::forTenant()` was removed in 0.6 — it collided with the tenant singletons' `forTenant()`).

## Next Steps

- [Pages](pages.md) — Create multilingual pages
- [Articles](articles.md) — Write articles in multiple languages
- [Navigation](navigation.md) — Build multilingual navigation menus
