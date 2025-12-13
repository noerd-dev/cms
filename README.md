# noerd/cms

The noerd package is required. Make sure the project is already initialized as a Git repository.
```
composer require noerd/noerd
php artisan noerd:install
```
noerd/media is required
```
git submodule add git@github.com:noerd-dev/business-hours.git app-modules/media
php artisan noerd:module media
composer update noerd/media
php artisan noerd:install-media
```

Install the package. Make sure you already initiated a git project.
```
git submodule add git@github.com:noerd-dev/cms.git app-modules/cms
php artisan noerd:module cms
composer update noerd/cms
php artisan noerd:install-cms
```