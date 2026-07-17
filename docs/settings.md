# CMS Settings

CMS settings provide tenant-level configuration for the website, including homepage selection, analytics, and cookie consent.

## File Locations

Livewire Component:
```
app-modules/cms/resources/views/components/cms-settings-detail.blade.php
```

Configuration:
```
app-modules/cms/config/noerd_cms.php
```

## Settings

Navigate to `/cms/settings` to configure:

| Setting | Description |
|---------|-------------|
| Homepage | Select which CMS page serves as the website homepage |
| Form Recipients | Email addresses receiving all form submissions (comma-separated for multiple recipients) |
| Google Analytics ID | Google Analytics tracking ID (e.g., `UA-XXXXXXXX-X` or `G-XXXXXXXXXX`) |
| Cookie Banner | Toggle the cookie consent banner on the website |

## CmsSetting Model

The `CmsSetting` model (`cms_settings` table):

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | integer | Tenant scope |
| `homepage_page_id` | integer | Reference to the homepage Page record |
| `google_analytics_id` | string | Analytics tracking ID |
| `form_recipients` | string | Comma-separated email addresses receiving form submissions (`CmsSetting::formRecipientsForTenant()` returns the parsed list) |
| `show_cookie_banner` | boolean | Whether to show the cookie banner |

## Configuration File

```php
// app-modules/cms/config/noerd_cms.php
return [
    'website_url' => env('CMS_WEBSITE_URL'),
];
```

Set the `CMS_WEBSITE_URL` environment variable to configure the base URL used for live preview links in the page editor.

## Next Steps

- [Pages](pages.md) — Create pages including the homepage
- [Global Parameters](global-parameters.md) — Manage site-wide key-value settings
