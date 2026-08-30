# Collections

Collections are dynamic content types stored in the database. They allow you to create custom data structures (e.g., services, projects, customers, sliders) without writing code.

## File Locations

YAML Configurations (management UI):
```
app-configs/cms/lists/collection-definitions-list.yml
app-configs/cms/details/collection-definition-detail.yml
```

Livewire Components:
```
app-modules/cms/resources/views/components/collection-definitions-list.blade.php
app-modules/cms/resources/views/components/collection-definition-detail.blade.php
app-modules/cms/resources/views/components/collection-entries-list.blade.php
```

## Collection Definitions

Each collection is defined by a per-tenant row in the `collection_definitions` table (model: `Noerd\Cms\Models\CollectionDefinition`) and resolved at runtime through the `CollectionDefinitionRepositoryContract`.

| Column | Description |
|--------|-------------|
| `tenant_id` | Owning tenant |
| `filename` | Lowercase, hyphenated identifier (used in URLs, e.g. `/cms/collections?key=services`) |
| `key` | Unique identifier for the collection (uppercase, referenced by templates) |
| `title` | Display title for a single entry (translation key) |
| `title_list` | Display title for the list view (translation key) |
| `description` | Optional description |
| `has_page` | Whether each entry generates a page (see below) |
| `fields` | JSON array of field definitions (names prefixed with `detailData.`) |

Example:

```php
CollectionDefinition::create([
    'tenant_id' => $tenantId,
    'filename' => 'sliders',
    'key' => 'SLIDERS',
    'title' => 'cms_slider',
    'title_list' => 'cms_sliders',
    'description' => '',
    'has_page' => false,
    'fields' => [
        ['name' => 'detailData.image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
    ],
]);
```

## `hasPage: true` vs `hasPage: false`

### `hasPage: true`

Each collection entry creates a full page with its own URL slug, layout, and page elements. Entries are stored as `Page` records with a `collection_id` linking them to the collection. This is used for content like project pages, service pages, or any content that needs its own dedicated URL.

### `hasPage: false`

Entries are simple data records without a dedicated page. The data is stored in the `data` JSON column on the `Page` model. This is used for things like sliders, testimonials, or feature lists that are displayed as part of other pages.

## Field Types

| Type | Description |
|------|-------------|
| `translatableText` | Text input with per-language values |
| `translatableRichText` | Rich text editor with per-language values |
| `translatableTextarea` | Textarea with per-language values |
| `text` | Simple text input |
| `image` | Image upload field |

## Creating Collection Definitions

Navigate to `/cms/collection-definitions` to manage collections through the UI. The collection definition detail view allows you to:

- Set the collection key, title, and description
- Define fields with name, label, and type
- Toggle `hasPage` to enable page generation

Definitions can also be seeded programmatically via the `CollectionDefinition` model or the repository's `save()` method.

## Collection Entries

Collection entries are listed at `/cms/collections`. The list view dynamically renders columns based on the collection's field definitions, using `CollectionHelper::getCollectionTable()` to build the table configuration.

## Data Storage

Collection entries use the `Page` model:

| Column | Description |
|--------|-------------|
| `collection_id` | Reference to the collection |
| `name` | Translatable page name (array) |
| `slug` | Translatable URL slug (array) |
| `data` | JSON field data for the collection |

## FieldTypeConverter

When saving a collection page, the `FieldTypeConverter` service automatically converts field data based on the collection definition:

- **Translatable types** (`translatableText`, `translatableRichText`, `translatableTextarea`): Data is stored as `{ "de": "value", "en": "value" }`
- **Non-translatable types**: The appropriate language value is extracted from the array

This conversion runs automatically in the `Page` model's boot method.

## CollectionHelper

The `CollectionHelper` class provides methods for working with collection definitions:

- `getCollectionFields(collection)` — Resolves field definitions via the repository
- `getCollectionTable(collection)` — Builds table column configuration for list views

It is registered as a singleton in the service container for mockability in tests.

## Navigation Integration

Collections can be dynamically added to the CMS navigation using the `dynamic` property:

```yaml
- title: Collections
  dynamic: collections
- title: Page Collections
  dynamic: page-collections
```

This automatically creates navigation entries for each defined collection.

## Next Steps

- [Pages](pages.md) — Understand how collection pages work
- [Elements](elements.md) — Add content blocks to collection pages
- [Navigation](navigation.md) — Link collection entries in navigation
