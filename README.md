# noerd/cms

The noerd package is required. Make sure the project is already initialized as a Git repository.
```
composer require noerd/noerd
php artisan noerd:install
```

noerd/media is required
```
git submodule add git@github.com:noerd-dev/media.git app-modules/media
composer require noerd/media
```

Install the package. Make sure you already initiated a git project.
```
git submodule add git@github.com:noerd-dev/cms.git app-modules/cms
composer require noerd/cms

php artisan noerd:install-cms
```