## Noerd CMS

Add

    "repositories": [
        {
            "type": "path",
            "url": "app-modules/*",
            "options": {
                "symlink": true
            }
        }
    ]


and 


        "noerd/cms": "*",
        "noerd/website": "*",
        "noerd/noerd": "^1.0"


to composer.json and run composer update

change auth.php to noerd user

php artisan noerd:install
php artisan noerd:install-cms
php artisan migrate
