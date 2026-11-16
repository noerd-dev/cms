<?php

declare(strict_types=1);

/*
 | The CMS configuration, read as `config('cms.*')`. Publish it with
 | `php artisan noerd:install-cms` (or `vendor:publish --tag=cms-config`); a
 | project that still has the earlier `config/noerd_cms.php` keeps it working
 | for the 0.6 line, but should rename it to `config/cms.php`.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Website URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the website. This is used for generating absolute URLs
    | and links in the CMS.
    |
    */
    'website_url' => env('CMS_WEBSITE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Custom Page Elements Path
    |--------------------------------------------------------------------------
    |
    | Optional additional directory (relative to the project root) that is
    | scanned for page element components on top of the module and project
    | element locations.
    |
    */
    'page_elements_path' => env('CMS_PAGE_ELEMENTS_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Page Layouts Path
    |--------------------------------------------------------------------------
    |
    | Directory (relative to the project root) holding the frontend layouts a
    | page can choose from. Every `*.blade.php` file that does not start with
    | an underscore is offered in the page editor. Left empty, the layouts are
    | found in the `components/layouts` folder of the `website` view namespace
    | (the installed website module), wherever that module lives.
    |
    */
    'layout_path' => env('CMS_LAYOUT_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Reserved Slug Prefixes
    |--------------------------------------------------------------------------
    |
    | Path prefixes no page slug may start with, on top of the built-in list
    | (PageSlugService::RESERVED_PREFIXES: /api, /cms, /livewire, /noerd,
    | /storage, …). Add the prefixes of the project's own routes here, e.g.
    | ['/shop', '/account'].
    |
    */
    'reserved_slug_prefixes' => [],

    /*
    |--------------------------------------------------------------------------
    | Collection Field Types
    |--------------------------------------------------------------------------
    |
    | The field types a collection definition may use for its entries, keyed by
    | the YAML type with the label shown in the definition editor.
    |
    */
    'collection_field_types' => [
        'text' => 'Text',
        'translatableText' => 'Translatable Text',
        'translatableTextarea' => 'Translatable Textarea',
        'translatableRichText' => 'Translatable Rich Text',
        'image' => 'Image',
        'email' => 'Email',
        'tel' => 'Phone',
        'number' => 'Number field',
        'checkbox' => 'Checkbox',
        'pageRelation' => 'Page',
        'elementCollection' => 'Element Collection',
    ],
];
