# Navigation Management

The CMS navigation system allows you to create hierarchical, multi-language navigation menus for your website. Navigation items can link to CMS pages or external URLs.

## File Locations

YAML Configuration:
```
app-configs/cms/details/navigation-detail.yml
app-configs/cms/lists/navigation-list.yml
```

Livewire Components:
```
app-modules/cms/resources/views/components/navigation-list.blade.php
app-modules/cms/resources/views/components/navigation-detail.blade.php
```

## Creating Navigation Items

Navigate to `/cms/navigation` to manage navigation items. Each item has the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `navigation_key` | text | Group key for the menu (e.g., `main`, `footer`) |
| `name` | translatableText | Display name in each active language |
| `page_id` | pageRelation | Link to a CMS page |
| `link` | text | External URL (alternative to page_id) |
| `new_tab` | checkbox | Open link in a new browser tab |

## YAML Configuration

```yaml
title: Navigationspunkt
description: ''
fields:
  - name: navigationData.navigation_key
    label: Key
    type: text
    colspan: 6
  - name: navigationData.name
    label: Name
    type: translatableText
    colspan: 6
  - name: navigationData.page_id
    label: Page
    type: pageRelation
    colspan: 6
  - name: navigationData.link
    label: 'Link (URL)'
    type: text
    colspan: 6
  - name: navigationData.new_tab
    label: 'New Tab'
    type: checkbox
    colspan: 6
```

## Navigation Keys

Navigation items are grouped by their `navigation_key`. Common keys include:

| Key | Usage |
|-----|-------|
| `main` | Primary website navigation |
| `footer` | Footer navigation links |

Use any string as a key to create custom navigation groups. The frontend website queries navigation items by key to render specific menus.

## Page vs External Link

Each navigation item links to either a CMS page or an external URL:

- **Page link**: Select a page via the relation field. The navigation item inherits the page's URL.
- **External link**: Enter a full URL in the `link` field. Use this for links to external websites.

These are mutually exclusive — use one or the other.

## Hierarchical Navigation

Navigation supports parent-child relationships via `parent_id`:

- Items with no parent are top-level entries
- Child items are nested under their parent
- Sort order is controlled by `sort_order`
- Children are automatically ordered by `sort_order`

## Multi-Language Names

The `name` field uses `translatableText`, storing a separate value for each active CMS language:

```json
{
  "de": "Über uns",
  "en": "About Us"
}
```

The frontend displays the name matching the visitor's selected language.

## Navigation Model

The `Navigation` model (`cms_navigations` table):

| Column | Description |
|--------|-------------|
| `navigation_key` | Menu group identifier |
| `name` | Translatable display name (JSON) |
| `page_id` | Reference to a CMS page (nullable) |
| `link` | External URL (nullable) |
| `new_tab` | Open in new tab (boolean) |
| `parent_id` | Parent navigation item (nullable) |
| `sort_order` | Display order within the group |

Relations:
- `page()` — belongsTo Page
- `collection()` — belongsTo Collection
- `parent()` — belongsTo self
- `children()` — hasMany self (ordered by sort_order)

## Next Steps

- [Pages](pages.md) — Create pages to link in navigation
- [Collections](collections.md) — Add dynamic collection links
- [Languages](languages.md) — Configure languages for multilingual navigation
