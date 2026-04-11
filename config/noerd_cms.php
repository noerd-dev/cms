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
    | Collection Definitions
    |--------------------------------------------------------------------------
    |
    | The storage mode (yaml | database) is shared with Setup collections and
    | lives in the noerd.collections.* namespace. This module only owns its
    | YAML source path.
    |
    */
    'collections' => [
        'yaml_path' => 'app-configs/cms/collections',
    ],
];
