<?php

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
];
