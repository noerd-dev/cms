# Noerd CMS Framework

Install the package
```
mkdir app-modules/cms
git submodule add git@github.com:noerd-dev/cms.git app-modules/cms
mkdir app-modules/media
git submodule add git@github.com:noerd-dev/media.git app-modules/media
php artisan make:module cms
php artisan make:module media
php artisan noerd:install-cms
php artisan migrate
```