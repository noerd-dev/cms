# CMS Settings

CMS settings provide tenant-level configuration for the website, including homepage selection, the cookie banner and a Google tag. The screen is a noerd **settings page**: a tenant singleton built on the `NoerdSettingsPage` trait, laid out exclusively by a settings YAML.

## File Locations

Livewire component:
```
app-modules/cms/resources/views/components/cms-settings-page.blade.php
```

Settings YAML (module copy plus the installed project copy, kept in sync):
```
app-modules/cms/app-configs/cms/settings/cms-settings-page.yml
app-configs/cms/settings/cms-settings-page.yml
```

Package configuration:
```
config/cms.php
```

## Settings

Navigate to `/cms/settings` to configure:

| Setting | Description |
|---------|-------------|
| Homepage | Select which CMS page serves as the website homepage (`homepageSelect` field type, a dynamic page picker registered by the CMS ServiceProvider) |
| Form Recipients | Email addresses receiving all form submissions (comma-separated for multiple recipients) |
| Cookie Banner | Toggle the cookie consent banner on the website |
| Cookie Consent Duration | How long an accept/decline choice is stored (30/90/182/365 days; shown only while the banner is enabled) |
| Google Tag | A Tag Manager container (`GTM-XXXXXXX`) or a Google tag / Analytics ID (`G-XXXXXXXXXX`); shown only while the banner is enabled |

The component keeps a custom `store()` override for the validation the YAML cannot express (the comma-separated recipient emails, the tenant-scoped homepage check, the consent-duration range and the Google tag pattern `CmsSetting::GOOGLE_TAG_PATTERN`) and ends with the standard `validateFromLayout()` / `persistSettings()` tail. The singleton row is created on the first save — never as a render side effect.

## Cookie Banner and Google Tag

`Noerd\Cms\Services\WebsiteTrackingService::forRequest($tenantId, $host)` is the ONE gate the website
layout asks:

- the banner (`devrabiul/laravel-cookie-consent`) shows while it is switched on; acceptance and
  rejection are stored for the same duration (`cookieLifetimeInDays()`)
- the Google tag exists only together with the banner, matches `GOOGLE_TAG_PATTERN` (which also keeps
  the value safe inside JavaScript) and is loaded by the banner only after the visitor's consent to
  analytics cookies
- it only runs on a host that shares no session with the backend — never the `app.url` host, never a
  host below `session.domain`: a Tag Manager container can inject any JavaScript, and on the backend's
  origin it would run with the session of whoever opens the website

The website boilerplate renders it through `website::partials.tracking-head` (in `<head>`) and
`website::partials.cookie-consent` (before `</body>`).

## Starter Homepage

`noerd:install-cms` creates a starter homepage (a page plus the settings row naming it) for a CMS
tenant that has no page and no settings row yet. `noerd:update-cms` never creates one — a homepage an
admin cleared stays cleared.

## CmsSetting Model

The `CmsSetting` model (`cms_settings` table, one row per tenant — enforced by a unique index on `tenant_id`) is a tenant singleton (`Noerd\Traits\TenantSingleton`): `CmsSetting::forTenant($tenantId)` answers the tenant's row, or an unsaved instance with `tenantDefaults()` before the first save.

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | integer | Tenant scope (unique) |
| `homepage_page_id` | integer | Reference to the homepage Page record |
| `google_analytics_id` | string | The Google tag ID (`GTM-…` or `G-…`) |
| `form_recipients` | string | Comma-separated email addresses receiving form submissions (`CmsSetting::formRecipientsForTenant()` returns the parsed list) |
| `show_cookie_banner` | boolean | Whether to show the cookie banner |
| `cookie_lifetime_days` | integer | Consent storage duration; `cookieLifetimeInDays()` falls back to the cookie-consent package config, then to 182 days |

## Configuration File

`config/cms.php` (`cms.*`, published by `noerd:install-cms` or `vendor:publish --tag=cms-config`):

```php
return [
    'website_url' => env('CMS_WEBSITE_URL', ''),
    'page_elements_path' => env('CMS_PAGE_ELEMENTS_PATH'),
    'layout_path' => env('CMS_LAYOUT_PATH'),       // null: the `website` view namespace's components/layouts
    'reserved_slug_prefixes' => [],
    'collection_field_types' => [/* type => label */],
];
```

Up to 0.5 the file was `config/noerd_cms.php` (`noerd_cms.*`). A project that still has it keeps working
for the 0.6 line — its values are merged over the defaults while there is no `config/cms.php` — but
should rename the file; `noerd:update-cms` reminds of it and never overwrites either file.

Set the `CMS_WEBSITE_URL` environment variable to configure the base URL used for live preview links in the page editor. `CMS_PAGE_ELEMENTS_PATH` optionally adds an extra element directory (see [Elements](elements.md)).

## Next Steps

- [Pages](pages.md) — Create pages including the homepage
- [Global Parameters](global-parameters.md) — Manage site-wide key-value settings
