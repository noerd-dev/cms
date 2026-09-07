# Redirects

Redirects map a retired path (a renamed or removed page) to an existing page, so
old links and search-engine results keep working.

## File Locations

YAML Configurations:
```
app-configs/cms/details/redirect-detail.yml
app-configs/cms/lists/redirects-list.yml
```

Livewire Components:
```
app-modules/cms/resources/views/components/redirect-detail.blade.php
app-modules/cms/resources/views/components/redirects-list.blade.php
```

## Managing Redirects

Navigate to `/cms/redirects` to manage redirects. Each redirect has:

| Field | Type | Description |
|-------|------|-------------|
| `source_path` | text | The old path (normalized: lowercase, no trailing slash, no query or fragment) |
| `target_page_id` | pageRelation | The page the path redirects to |
| `is_active` | checkbox | Only active redirects are applied (defaults to active) |

The detail rejects the start page (`/`), a source path that is already
redirected, and a path that already belongs to the selected page.

## Redirect Model

`Noerd\Cms\Models\Redirect` (table `cms_redirects`, unique per tenant and
`source_path`):

- Uses `BelongsToTenant` and `$guarded = []`
- `normalizePath(string $path): string` — the canonical form both the editor and
  the website frontend use to compare paths (`'Agentur/'` → `/agentur`)
- `targetPage()` belongs to `Page`

The website boilerplate carries an identical copy of `normalizePath()` in its
own `Redirect` model, because the frontend matches incoming paths without
loading the CMS backend. Both copies are covered by the parity test in the
module suite — change them together.

## Next Steps

- [Pages](pages.md) — Manage the pages redirects point to
- [Navigation](navigation.md) — Keep the menu in sync after renaming pages
