# noerd/cms

Multi-tenant, multi-language CMS module for Laravel. Provides pages with a
visual element builder, database-backed collections, hierarchical navigation,
forms with email notifications, articles, and a REST API for form
submissions.

## Requirements

- `noerd/noerd` (base framework)
- `noerd/media` (file/media storage)
- `noerd/communication` (email delivery for form notifications)

## Installation

Ensure the project is an initialized Git repository, then install dependencies:

```bash
composer require noerd/noerd
php artisan noerd:install

git submodule add git@github.com:noerd-dev/media.git app-modules/media
composer require noerd/media

git submodule add git@github.com:noerd-dev/communication.git app-modules/communication
composer require noerd/communication
```

Install the CMS module:

```bash
git submodule add git@github.com:noerd-dev/cms.git app-modules/cms
composer require noerd/cms

php artisan noerd:install-cms
```

The install command publishes the YAML configs and the module configuration,
registers the tenant app, runs the migrations and seeds a starter homepage for
every tenant. The default tenant language is created automatically whenever a
tenant is created.

## Configuration

`app-modules/cms/config/noerd_cms.php`:

```php
return [
    'website_url' => env('CMS_WEBSITE_URL', ''),
    'page_elements_path' => env('CMS_PAGE_ELEMENTS_PATH'),
];
```

Set `CMS_WEBSITE_URL` in `.env` for live-preview links; `CMS_PAGE_ELEMENTS_PATH`
optionally adds an extra page-element directory.

## Core Features

| Feature | Description | Docs |
|---|---|---|
| Pages | Website pages with a drag-and-drop element builder. | [docs/pages.md](docs/pages.md) |
| Elements | Reusable content blocks (`.blade.php` + `.yml` pair). | [docs/elements.md](docs/elements.md) |
| Collections | Database-defined content types (services, projects, sliders). | [docs/collections.md](docs/collections.md) |
| Element Collections | Per-element row lists owned by a page entry or element instance. | see below |
| Navigation | Hierarchical, multi-language site navigation. | [docs/navigation.md](docs/navigation.md) |
| Forms | YAML-defined forms with email notifications. | [docs/forms.md](docs/forms.md) |
| Articles | Blog/news posts with authors and publication dates. | [docs/articles.md](docs/articles.md) |
| Languages | Per-tenant language configuration. | [docs/languages.md](docs/languages.md) |
| Global Parameters | Key/value pairs for site-wide settings. | [docs/global-parameters.md](docs/global-parameters.md) |
| Settings | Homepage selection, Analytics, cookie banner. | [docs/settings.md](docs/settings.md) |
| REST API | Token-authenticated endpoints for form submissions. | [docs/api.md](docs/api.md) |

## Element Collections

Repeater-style fields are managed as **element collections**: hidden
collections owned by either a page entry (`page_id`) or a single element
instance (`element_page_id`). Each owner+field pair maps to one collection
via a deterministic key (`ELEMENT_{ownerId}_{FIELD}` or
`ELEMENT_EP_{elementPageId}_{FIELD}`).

Use the field type `element-collection` in any element or detail YAML:

```yaml
fields:
  - name: detailData.items
    label: Items
    type: element-collection
    colspan: 12
    fields:
      - name: title
        label: Title
        type: translatableText
        colspan: 6
      - name: description
        label: Description
        type: translatableTextarea
        colspan: 12
```

Rows are edited in a nested modal (`cms::element-collection-row-detail`).
At render time, `HandlesPageElements::processPageElements()` merges row data
back into the owner's data under `owner_field`, so element Blade templates
can iterate the field directly:

```blade
@foreach(($element->items ?? []) as $item)
    <h4>{{ $item['title'] ?? '' }}</h4>
    <p>{{ $item['description'] ?? '' }}</p>
@endforeach
```

Key classes:

- `Noerd\Cms\Services\ElementCollectionService` — `ensure()` / `importItems()` / `schemaFor()`
- `Noerd\Cms\Repositories\ElementAwareCollectionDefinitionRepository` — resolves the schema from the stored `element_fields` column
- `Noerd\Cms\Traits\HandlesPageElements` — frontend rendering pipeline

## Routes

All CMS routes are prefixed `/cms` and protected by the `noerd` middleware
group and `app-access:cms`.

| Route | Component |
|---|---|
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

## Database Tables

| Table | Purpose |
|---|---|
| `pages` | Pages and collection entries |
| `element_page` | Page-element associations with JSON data |
| `collections` | Per-tenant collection instances (including element collections) |
| `collection_definitions` | Collection definitions (fields, titles, hasPage) |
| `cms_navigations` | Navigation items with hierarchy |
| `form_types` | Form definitions synced from YAML |
| `form_requests` | Submitted form data |
| `cms_languages` | Language configuration per tenant |
| `cms_settings` | CMS settings per tenant |
| `global_parameters` | Key/value site-wide parameters |
| `authors` | Article authors |
| `articles` | Articles |

## File Locations

YAML configurations (project-level):

```
app-configs/cms/lists/          # *-list.yml
app-configs/cms/details/        # *-detail.yml
app-configs/cms/settings/       # settings-page.yml
app-configs/cms/forms/          # form definitions
app-configs/cms/navigation.yml  # CMS admin navigation
```

Module source:

```
app-modules/cms/src/Models/         # Eloquent models
app-modules/cms/src/Helpers/        # FieldHelper, CollectionHelper
app-modules/cms/src/Services/       # FormTypeSyncService, ElementCollectionService, FieldTypeConverter
app-modules/cms/src/Repositories/   # Collection definition repositories
app-modules/cms/src/Traits/         # HandlesPageElements, LanguageFilterTrait
app-modules/cms/routes/             # Web and API routes
app-modules/cms/resources/views/    # Livewire components and Blade views
app-modules/cms/config/             # noerd_cms.php
app-modules/cms/database/           # Migrations, factories, seeders
```

## Multi-Tenancy

Content models use the `BelongsToTenant` trait — content is automatically
scoped to the current tenant (`CmsSetting` is a tenant singleton keyed
explicitly, `ElementPage` is scoped through its page). When a new tenant is
created, a default language is set up via
`CmsLanguage::ensureDefaultLanguageForTenant()`.

## Access Control

The `canCms` gate checks whether the current user's tenant has the CMS app
enabled. Routes use the `app-access:cms` middleware to enforce this.

## Translations

Translation keys are English text; only `de.json` is shipped at
`app-modules/cms/resources/lang/de.json`. English works by fallback. Entries
where English equals German are omitted.

## Testing

```bash
php artisan test --compact app-modules/cms/tests
```

The module ships unit tests, feature tests, and component (Livewire) tests
covering collections, element collections, forms, navigation, and the API.

## Further Reading

- [docs/overview.md](docs/overview.md) — module overview
- [docs/pages.md](docs/pages.md), [docs/elements.md](docs/elements.md), [docs/collections.md](docs/collections.md)
- [docs/forms.md](docs/forms.md), [docs/api.md](docs/api.md)
- [docs/languages.md](docs/languages.md), [docs/navigation.md](docs/navigation.md)
- [docs/articles.md](docs/articles.md), [docs/settings.md](docs/settings.md), [docs/global-parameters.md](docs/global-parameters.md)
