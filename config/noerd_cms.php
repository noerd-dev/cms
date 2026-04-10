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
    | Controls where collection schemas are stored.
    |
    | Supported modes:
    |   - "yaml":     Schemas live in the directory configured by "yaml_path".
    |                 The management UI is hidden. Changes must be deployed
    |                 via committed YAML files.
    |   - "database": Schemas live in the collection_definitions table
    |                 (per tenant). The management UI is enabled.
    |
    | The "show_definitions_ui" key is derived automatically and used by the
    | navigation filter — do not set it manually in the environment.
    |
    */
    'collections' => [
        'mode' => env('CMS_COLLECTIONS_MODE', 'yaml'),
        'show_definitions_ui' => env('CMS_COLLECTIONS_MODE', 'yaml') === 'database',
        'yaml_path' => 'app-configs/cms/collections',
    ],
];
