# Noerd CMS Module

Install the package. Make sure you already initiated a git project and noerd is already installed.
```
git submodule add git@github.com:noerd-dev/cms.git app-modules/cms
php artisan noerd:module cms
composer update noerd/cms
```

Install Command to copy files and configs
```
php artisan noerd:install-cms
```

Run database migrations
```
php artisan migrate
```

Compile the assets
```
npm install
npm run build
```
