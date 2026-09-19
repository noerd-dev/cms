# noerd/cms

Multi-tenant, multilingual CMS for Laravel Livewire. Provides content management with a
visual element builder for pages and custom collections.

## Installation

```bash
composer require noerd/cms

php artisan noerd:install-cms
```

## Configuration

`noerd:install-cms` publishes `config/noerd_cms.php` (also available through
`php artisan vendor:publish --tag=cms-config`; `noerd:update-cms` publishes it
when missing):

```php
return [
    'website_url' => env('CMS_WEBSITE_URL', ''),
    'page_elements_path' => env('CMS_PAGE_ELEMENTS_PATH'),
    'layout_path' => env('CMS_LAYOUT_PATH', 'app-modules/website/resources/views/components/layouts'),
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