# noerd/cms

Multi-tenant, multilingual CMS for Laravel Livewire. Provides content management with a
visual element builder for pages and custom collections.

## Installation

```bash
composer require noerd/cms

php artisan noerd:install-cms
```

Upgrading from 0.5: `composer update noerd/cms`, `php artisan migrate`, `php artisan noerd:update-cms`
(it moves the renamed screens' YAML, rewrites the form YAML to the 0.6 vocabulary and syncs the
form types) and rename `config/noerd_cms.php` to `config/cms.php`. See the release notes.

## Configuration

`noerd:install-cms` publishes `config/cms.php` (`cms.*`; also available through
`php artisan vendor:publish --tag=cms-config`; `noerd:update-cms` publishes it
when the project has no CMS config at all). A project still on the earlier
`config/noerd_cms.php` keeps it working for the 0.6 line — rename it:

```php
return [
    'website_url' => env('CMS_WEBSITE_URL', ''),
    'page_elements_path' => env('CMS_PAGE_ELEMENTS_PATH'),
    'layout_path' => env('CMS_LAYOUT_PATH'), // empty: the website module's components/layouts
    'reserved_slug_prefixes' => [],
    'collection_field_types' => [/* type => label */],
];
```

Set `CMS_WEBSITE_URL` in `.env` for live-preview links; `CMS_PAGE_ELEMENTS_PATH`
optionally adds an extra page-element directory; `CMS_LAYOUT_PATH` points the
page editor at the frontend layouts; `collection_field_types` lists the field
types a collection definition may use.

## Element Collections

Repeater-style fields are managed as **element collections**: hidden
collections owned by either a page entry (`page_id`) or a single element
instance (`element_page_id`). Each owner+field pair maps to one collection
via a deterministic key (`ELEMENT_{ownerId}_{FIELD}` or
`ELEMENT_EP_{elementPageId}_{FIELD}`).

Use the field type `elementCollection` in any element or detail YAML:

```yaml
fields:
  - name: detailData.items
    label: Items
    type: elementCollection
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