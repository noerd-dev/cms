# Pages

Pages are the core content unit of the CMS. Each page has a multilingual name and slug, an optional layout, SEO metadata, and a collection of reusable elements that form the page content.

## File Locations

YAML Configuration:
```
app-configs/cms/details/page-detail.yml
app-configs/cms/lists/pages-list.yml
```

Livewire Components:
```
app-modules/cms/resources/views/components/page-detail.blade.php
app-modules/cms/resources/views/components/pages-list.blade.php
```

## Creating and Editing Pages

Pages are managed through the pages list (`/cms/pages`). Click "New Page" to create a new page or click an existing page to edit it.

The page detail view has two tabs:

### Tab 1: General

| Field | Type | Description |
|-------|------|-------------|
| `name` | translatableText | Page title in each active language |
| `slug` | translatableText | URL slug (auto-generated from name) |
| `is_active` | checkbox | Whether the page is published |
| `layout` | picklist | Layout template for the page |

### Tab 2: SEO Metadata

| Field | Type | Description |
|-------|------|-------------|
| `meta_title` | translatableText | SEO title tag |
| `meta_description` | translatableText | SEO meta description |

## YAML Configuration

```yaml
title: ''
description: ''
tabs:
  - number: 1
    label: noerd_general
  - number: 2
    label: cms_metadata
fields:
  - type: block
    title: cms_page_info
    tab: 1
    fields:
      - name: pageData.name
        label: Title
        type: translatableText
        colspan: 5
      - name: pageData.slug
        label: URL
        type: translatableText
        colspan: 5
      - name: pageData.is_active
        label: cms_label_is_active
        type: checkbox
        colspan: 2
  - type: block
    title: noerd_layout_settings
    tab: 1
    fields:
      - name: pageData.layout
        label: Layout
        type: picklist
        picklistField: layoutOptions
        colspan: 12
  - type: block
    title: cms_seo_metadata
    tab: 2
    fields:
      - name: pageData.meta_title
        label: cms_meta_title
        type: translatableText
        colspan: 6
      - name: pageData.meta_description
        label: cms_meta_description
        type: translatableText
        colspan: 6
```

## Slug Generation

Slugs are auto-generated from the page name. For non-default languages, a language prefix is added automatically:

- Default language (e.g., English): `/about-us`
- Non-default language (e.g., German): `/de/ueber-uns`

## Layouts

Layouts are auto-detected from the website module:

```
app-modules/website/resources/views/components/layouts/
```

Each `.blade.php` file in that directory becomes an available layout option in the page editor.

## Page Elements

Below the page fields, the page detail view shows the element builder. Elements are reusable content blocks that can be added, sorted, and edited on each page. See [Elements](elements.md) for details.

## Page Model

The `Page` model stores pages and collection entries:

- `name`, `slug`, `meta_title`, `meta_description` are cast to arrays for multi-language support
- `is_active` defaults to `true`
- `data` is cast to an array (used by collection entries)
- Relations: `elements()` (hasMany ElementPage), `collection()` (belongsTo Collection)

When saving a page that belongs to a collection, the `FieldTypeConverter` automatically converts field data between translatable and non-translatable formats based on the collection definition.

## Language Filtering

The pages list supports language filtering via the `LanguageFilterTrait`. The current language is stored in the session, and the list filters pages to show names and slugs in the selected language.

## Next Steps

- [Elements](elements.md) — Add content blocks to pages
- [Collections](collections.md) — Create dynamic page types
- [Navigation](navigation.md) — Link pages in site navigation
