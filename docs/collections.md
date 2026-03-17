# Collections

Collections are dynamic content types defined via YAML. They allow you to create custom data structures (e.g., services, projects, customers, sliders) without writing code.

## File Locations

Collection definitions:
```
app-configs/cms/collections/{collection-key}.yml
```

YAML Configurations:
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

## Collection Definition YAML

Each collection is defined by a YAML file in `app-configs/cms/collections/`:

Example: `app-configs/cms/collections/sliders.yml`

```yaml
title: cms_slider
titleList: cms_sliders
key: SLIDERS
buttonList: cms_new_slider
description: ''
hasPage: false
fields:
  - name: image
    label: Image
    type: image
    colspan: 6
```

Example: `app-configs/cms/collections/pages.yml`

```yaml
title: Page
titleList: Pages
key: PAGES
buttonList: 'Neue Seite'
description: ''
hasPage: true
fields:
  - name: pageData.name
    label: Name
    type: translatableText
    colspan: 6
  - name: image
    label: Bild
    type: image
    colspan: 6
```

## Collection Properties

| Property | Description |
|----------|-------------|
| `title` | Display title for a single entry (translation key) |
| `titleList` | Display title for the list view (translation key) |
| `key` | Unique identifier for the collection (uppercase) |
| `buttonList` | Label for the "New Entry" button (translation key) |
| `description` | Optional description |
| `hasPage` | Whether each entry generates a page (see below) |
| `fields` | Array of field definitions |

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

## Built-in Collections

| Collection | Key | hasPage | Description |
|------------|-----|---------|-------------|
| Pages | `PAGES` | true | General website pages |
| Services | `SERVICES` | true | Service pages |
| Projects | `PROJECTS` | true | Project/portfolio pages |
| Customers | `CUSTOMERS` | false | Customer logos/data |
| Sliders | `SLIDERS` | false | Homepage slider images |

## Creating Collection Definitions

Collection definitions can be managed in two ways:

### 1. YAML Files (Recommended)

Create a YAML file in `app-configs/cms/collections/` following the structure above. The CMS automatically discovers these files.

### 2. Admin UI

Navigate to `/cms/collection-definitions` to manage collections through the UI. The collection definition detail view allows you to:

- Set the collection key, title, and description
- Define fields with name, label, and type
- Toggle `hasPage` to enable page generation
- Save generates/updates the corresponding YAML file

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

The `CollectionHelper` class provides methods for working with collection YAML configs:

- `getCollectionFields(collection)` — Loads field definitions from the YAML file
- `getCollectionTable(collection)` — Builds table column configuration for list views

It is registered as a singleton in the service container for mockability in tests.

## Navigation Integration

Collections can be dynamically added to the CMS navigation using the `dynamic` property:

```yaml
- title: cms_nav_collections
  dynamic: collections
- title: cms_nav_page_collections
  dynamic: page-collections
```

This automatically creates navigation entries for each defined collection.

## Next Steps

- [Pages](pages.md) — Understand how collection pages work
- [Elements](elements.md) — Add content blocks to collection pages
- [Navigation](navigation.md) — Link collection entries in navigation
