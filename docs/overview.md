# CMS Overview

The CMS module provides a multi-tenant, multi-language content management system for managing website pages, collections, navigation, forms, articles, and more.

## Installation

```bash
php artisan noerd:install-cms
```

This command sets up the required database tables and initial configuration.

## Core Features

| Feature | Description |
|---------|-------------|
| [Pages](pages.md) | Create and manage website pages with drag-and-drop elements |
| [Elements](elements.md) | Reusable content blocks (text, images, buttons, maps) added to pages |
| [Collections](collections.md) | Dynamic content types stored in the database (e.g., services, projects) |
| [Navigation](navigation.md) | Hierarchical site navigation with multi-language support |
| [Redirects](redirects.md) | Managed redirects for retired page paths |
| [Forms](forms.md) | YAML-defined forms with email notifications and API submissions |
| [Articles](articles.md) | Blog/news articles with authors and publication scheduling |
| [Languages](languages.md) | Multi-language support with per-tenant language configuration |
| [Global Parameters](global-parameters.md) | Key-value pairs for site-wide settings (footer text, social links) |
| [Settings](settings.md) | Homepage selection, Google Analytics, cookie banner |
| [API](api.md) | REST API for form submissions with token authentication |

## Database Tables

The CMS module creates the following tables:

| Table | Description |
|-------|-------------|
| `cms_pages` | Website pages and collection entries |
| `cms_page_elements` | Page-element associations with JSON data |
| `cms_collections` | Per-tenant collection instances (including element collections) |
| `cms_collection_definitions` | Collection definitions (fields, titles, hasPage) |
| `cms_navigations` | Navigation items with hierarchy |
| `cms_redirects` | Managed redirects for retired page paths |
| `cms_form_types` | Form definitions synced from YAML |
| `cms_form_requests` | Submitted form data |
| `cms_languages` | Language configuration per tenant |
| `cms_settings` | CMS settings per tenant |
| `cms_global_parameters` | Key-value site-wide parameters |
| `cms_authors` | Blog article authors |
| `cms_articles` | Blog articles |

## File Locations

YAML Configurations:
```
app-configs/cms/lists/          # List view configurations
app-configs/cms/details/        # Detail view configurations
app-configs/cms/forms/          # Form definitions
app-configs/cms/navigation.yml  # CMS navigation menu
```

Module Source:
```
app-modules/cms/src/Models/     # Eloquent models
app-modules/cms/src/Helpers/    # FieldHelper, CollectionHelper
app-modules/cms/src/Services/   # FormTypeSyncService, FieldTypeConverter
app-modules/cms/routes/         # Web and API routes
app-modules/cms/resources/      # Views, translations, lang files
app-modules/cms/config/         # noerd_cms.php (published to config/ by noerd:install-cms)
```

## Routes

All CMS web routes are prefixed with `/cms` and protected by the `noerd` middleware group and `app-access:cms`.

| Route | Component |
|-------|-----------|
| `/cms/` | Dashboard |
| `/cms/pages` | Pages list |
| `/cms/page/{modelId}` | Page editor |
| `/cms/navigation` | Navigation list |
| `/cms/redirects` | Redirects list |
| `/cms/collections` | Collection entries |
| `/cms/collection-definitions` | Collection definitions |
| `/cms/form-requests` | Form submissions |
| `/cms/form-types` | Form type management |
| `/cms/articles` | Articles list |
| `/cms/authors` | Authors list |
| `/cms/languages` | Language management |
| `/cms/global-parameters` | Global parameters |
| `/cms/settings` | CMS settings |

## Configuration

`noerd:install-cms` publishes the configuration to `config/noerd_cms.php` (the
module defaults stay merged, so every key is optional):

```php
return [
    'website_url' => env('CMS_WEBSITE_URL', ''),
    'page_elements_path' => env('CMS_PAGE_ELEMENTS_PATH'),
    'layout_path' => env('CMS_LAYOUT_PATH', 'app-modules/website/resources/views/components/layouts'),
    'collection_field_types' => [/* type => label */],
];
```

Set `CMS_WEBSITE_URL` in your `.env` file to configure the website URL used for live preview links, `CMS_PAGE_ELEMENTS_PATH` to add a project-level element directory and `CMS_LAYOUT_PATH` to point the page editor at the frontend layouts.

## Multi-Tenancy

Content models use the `BelongsToTenant` trait (`CmsSetting` is a tenant singleton keyed explicitly; `ElementPage` is scoped through its page). Content is automatically scoped to the current tenant. When a new tenant is created, a default English language is automatically set up via `CmsLanguage::ensureDefaultLanguageForTenant()`.

## Access Control

Access to the CMS is governed by the generic noerd app permission: the backend routes use the `app-access:cms` middleware, and tenant-scoped chrome (e.g. the "To Website" quick-menu button) checks `AccessHelper::canUseApp('CMS')` — the CMS app must be assigned to the selected tenant AND the user's app permission must allow it. There is no module-specific gate.

The tenant-wide configuration screens — settings, languages and collection definitions — are admin-only. They are registered with `ComponentAccessGuard::registerAdminComponents()`, so every mount (route, modal stack, component page) is rejected with 403 for a non-admin. Content screens (pages, navigation, redirects, forms, articles, global parameters) stay open to every user of the tenant, subject to the object permissions.

## Next Steps

- [Pages](pages.md) — Start creating website pages
- [Elements](elements.md) — Learn about the page builder system
- [Languages](languages.md) — Configure multi-language support
