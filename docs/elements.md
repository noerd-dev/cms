# Page Elements

Elements are reusable content blocks that can be added to pages via a drag-and-drop page builder. Each element is defined by a pair of files: a Blade template for rendering and a YAML file for field configuration.

## How Elements Work

1. Elements are discovered automatically from `resources/views/components/elements/` in any app-module
2. Each element has a `.blade.php` file and a matching `.yml` file
3. The YAML file defines the element's title, description, group, and editable fields
4. When added to a page, element data is stored in the `element_page` table as JSON

## File Locations

Element definitions (paired files):
```
app-modules/{module}/resources/views/components/elements/{element-name}.blade.php
app-modules/{module}/resources/views/components/elements/{element-name}.yml
```

Website boilerplate elements:
```
app-modules/cms/website-boilerplate/resources/views/components/elements/
```

## Element YAML Structure

Example: `text-block-1-column.yml`

```yaml
title: Text Block (1 Column)
description: A single column text block
group: Text
fields:
  - name: detailData.text
    label: Text
    type: translatableRichText
    colspan: 12
```

### Element YAML Properties

| Property | Description |
|----------|-------------|
| `title` | Display name in the element picker |
| `description` | Brief description shown in the picker |
| `group` | Category for grouping in the picker (e.g., Text, Images, Interactive) |
| `fields` | Array of editable fields (same field types as detail views) |

### Field Properties

| Property | Description |
|----------|-------------|
| `name` | Field path (e.g., `detailData.text`, `detailData.link`) |
| `label` | Field label |
| `type` | Field type: `translatableRichText`, `translatableText`, `text`, `checkbox`, `image` |
| `colspan` | Grid column span (1-12) |

## Element Picker

When adding an element to a page, a modal displays all available elements grouped by category. Elements are sorted alphabetically within each group, with "General" appearing first.

The element picker is powered by `FieldHelper::getAllElementsGrouped()`, which scans all modules for element definitions.

## Adding Elements to Pages

On the page detail view, use the element builder section to:

1. **Add** — Click the add button to open the element picker modal
2. **Sort** — Drag and drop elements to reorder them
3. **Edit** — Click an element to open its field editor
4. **Delete** — Remove an element from the page

## Live Preview

When editing an element, changes are dispatched via the `updateLiveElementData` event for real-time preview on the website frontend.

## Built-in Elements

The website boilerplate includes these default elements:

### Text Elements

| Element | Description | Fields |
|---------|-------------|--------|
| `text-block-1-column` | Single column text | `detailData.text` (translatableRichText) |
| `text-block-2-column` | Two column text | `detailData.text_left`, `detailData.text_right` (translatableRichText) |
| `text-block-2-column-2-3` | Asymmetric 2/3 column | `detailData.text_left`, `detailData.text_right` (translatableRichText) |
| `text-block-3-column` | Three column text | `detailData.text_1`, `detailData.text_2`, `detailData.text_3` (translatableRichText) |
| `text-block-home-page` | Homepage text block | `detailData.text` (translatableRichText) |

## Creating Custom Elements

To create a custom element, add two files to your module's element directory:

### 1. YAML Definition

```yaml
title: My Custom Element
description: A custom content block
group: Custom
fields:
  - name: detailData.heading
    label: Heading
    type: translatableText
    colspan: 12
  - name: detailData.content
    label: Content
    type: translatableRichText
    colspan: 12
  - name: image
    label: Image
    type: image
    colspan: 6
```

An `image` field stores the media id. When the page is rendered the id becomes the image's
**delivery URL**: a size-limited, cached variant from the media library (`web`, 1920px wide by
default) — an oversized upload is never sent to a visitor in full. Add `variant: teaser` to the
field to deliver another variant of the project's `config/media.php` `variants` list.

### 2. Blade Template

```blade
<div>
    <h2>{{ $heading }}</h2>
    <div>{!! $content !!}</div>
    @if($image)
        <img src="{{ $image }}" alt="{{ $heading }}">
    @endif
</div>
```

The element will be automatically discovered and available in the element picker.

## Data Storage

Element data is stored in the `element_page` table via the `ElementPage` model:

| Column | Description |
|--------|-------------|
| `page_id` | Reference to the page |
| `element_key` | Element type identifier (snake_case, e.g., `text_block_1_column`) |
| `data` | JSON object containing field values |
| `sort` | Sort order on the page |

Translatable fields are stored as JSON objects with language codes as keys:

```json
{
  "text": {
    "de": "German content",
    "en": "English content"
  }
}
```

## Key Concepts

- Element file names use **kebab-case** (`text-block-1-column.blade.php`), while `element_key` uses **snake_case** (`text_block_1_column`)
- `FieldHelper::getElementFields()` loads the YAML config colocated with the Blade component
- `FieldHelper::parseElementToData()` initializes translatable fields with empty values for each active language
- The `CMS_PAGE_ELEMENTS_PATH` environment variable (read through `config('noerd_cms.page_elements_path')`) can override the default element discovery path

## Next Steps

- [Pages](pages.md) — Learn about the page model and page management
- [Collections](collections.md) — Create custom content types with their own fields
