# CMS Settings

CMS settings provide tenant-level configuration for the website, including homepage selection, analytics, and cookie consent. The screen is a noerd **settings page**: a tenant singleton built on the `NoerdSettingsPage` trait, laid out exclusively by a settings YAML.

## File Locations

Livewire component:
```
app-modules/cms/resources/views/components/settings-page.blade.php
```

Settings YAML (module copy plus the installed project copy, kept in sync):
```
app-modules/cms/app-configs/cms/settings/settings-page.yml
app-configs/cms/settings/settings-page.yml
```

Package configuration:
```
app-modules/cms/config/noerd_cms.php
```

## Settings

Navigate to `/cms/settings` to configure:

| Setting | Description |
|---------|-------------|
| Homepage | Select which CMS page serves as the website homepage (`homepage-select` field type, a dynamic page picker registered by the CMS ServiceProvider) |
| Form Recipients | Email addresses receiving all form submissions (comma-separated for multiple recipients) |
| Cookie Banner | Toggle the cookie consent banner on the website |
| Cookie Consent Duration | How long an accept/decline choice is stored (30/90/182/365 days; shown only while the banner is enabled) |
| Google Analytics ID | Google Analytics measurement ID (e.g. `G-XXXXXXXXXX`; shown only while the banner is enabled) |

The component keeps a custom `store()` override for the validation the YAML cannot express (the comma-separated recipient emails, the tenant-scoped homepage check and the consent-duration range) and ends with the standard `validateFromLayout()` / `persistSettings()` tail. The singleton row is created on the first save — never as a render side effect.

## CmsSetting Model

The `CmsSetting` model (`cms_settings` table, one row per tenant — enforced by a unique index on `tenant_id`):

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | integer | Tenant scope (unique) |
| `homepage_page_id` | integer | Reference to the homepage Page record |
| `google_analytics_id` | string | Analytics measurement ID |
| `form_recipients` | string | Comma-separated email addresses receiving form submissions (`CmsSetting::formRecipientsForTenant()` returns the parsed list) |
| `show_cookie_banner` | boolean | Whether to show the cookie banner |
| `cookie_lifetime_days` | integer | Consent storage duration; `cookieLifetimeInDays()` falls back to the cookie-consent package config, then to 182 days |

## Configuration File

```php
// app-modules/cms/config/noerd_cms.php
return [
    'website_url' => env('CMS_WEBSITE_URL', ''),
    'page_elements_path' => env('CMS_PAGE_ELEMENTS_PATH'),
];
```

Set the `CMS_WEBSITE_URL` environment variable to configure the base URL used for live preview links in the page editor. `CMS_PAGE_ELEMENTS_PATH` optionally adds an extra element directory (see [Elements](elements.md)).

## Next Steps

- [Pages](pages.md) — Create pages including the homepage
- [Global Parameters](global-parameters.md) — Manage site-wide key-value settings
