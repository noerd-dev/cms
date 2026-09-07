<?php

declare(strict_types=1);

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
    | an underscore is offered in the page editor.
    |
    */
    'layout_path' => env('CMS_LAYOUT_PATH', 'app-modules/website/resources/views/components/layouts'),

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
        'number' => 'Number',
        'checkbox' => 'Checkbox',
        'pageRelation' => 'Page',
        'element-collection' => 'Element Collection',
    ],
];
