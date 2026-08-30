<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Base URL
    |--------------------------------------------------------------------------
    |
    | Prefix for media file paths rendered by the website elements. Leave empty
    | when the media files are served by the application itself.
    |
    */
    'media_url' => env('MEDIA_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Google Maps API Key
    |--------------------------------------------------------------------------
    |
    | Required by the google-map page element.
    |
    */
    'google_maps_key' => env('GOOGLE_MAPS_API_KEY', ''),
];
