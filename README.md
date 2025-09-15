# Noerd CMS Framework

Install the package. Make sure you already initiated a git project and noerd is already installed.
```
git submodule add git@github.com:noerd-dev/cms.git app-modules/cms
git submodule add git@github.com:noerd-dev/media.git app-modules/media
php artisan make:module cms
php artisan make:module media
composer update
php artisan noerd:install-cms
php artisan migrate
npm install
npm run build
```
