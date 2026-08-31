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
| `pages` | Website pages and collection entries |
| `element_page` | Page-element associations with JSON data |
| `collections` | Per-tenant collection instances (including element collections) |
| `collection_definitions` | Collection definitions (fields, titles, hasPage) |
| `cms_navigations` | Navigation items with hierarchy |
| `form_types` | Form definitions synced from YAML |
| `form_requests` | Submitted form data |
| `cms_languages` | Language configuration per tenant |
| `cms_settings` | CMS settings per tenant |
| `global_parameters` | Key-value site-wide parameters |
| `authors` | Blog article authors |
| `articles` | Blog articles |

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
app-modules/cms/config/         # noerd_cms.php configuration
```

## Routes

All CMS web routes are prefixed with `/cms` and protected by the `noerd` middleware group and `app-access:cms`.

| Route | Component |
|-------|-----------|
| `/cms/` | Dashboard |
| `/cms/pages` | Pages list |
| `/cms/page/{modelId}` | Page editor |
| `/cms/navigation` | Navigation list |
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

The CMS configuration file is located at:

```
app-modules/cms/config/noerd_cms.php
```

```php
return [
    'website_url' => env('CMS_WEBSITE_URL'),
];
```

Set `CMS_WEBSITE_URL` in your `.env` file to configure the website URL used for live preview links.

## Multi-Tenancy

Content models use the `BelongsToTenant` trait (`CmsSetting` is a tenant singleton keyed explicitly; `ElementPage` is scoped through its page). Content is automatically scoped to the current tenant. When a new tenant is created, a default English language is automatically set up via `CmsLanguage::ensureDefaultLanguageForTenant()`.

## Access Control

Access to the CMS is controlled by the `canCms` gate, which checks whether the current user's tenant has the CMS app enabled.

## Next Steps

- [Pages](pages.md) — Start creating website pages
- [Elements](elements.md) — Learn about the page builder system
- [Languages](languages.md) — Configure multi-language support
