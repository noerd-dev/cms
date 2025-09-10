<?php

namespace Noerd\Cms\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;
use Noerd\Cms\Commands\InstallWebsiteBoilerplateCommand;
use Noerd\Cms\Commands\NoerdCmsInstallCommand;
use Noerd\Cms\Middleware\CmsApiAuth;
use Noerd\Cms\Middleware\CmsMiddleware;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'cms');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'cms');
        $this->loadRoutesFrom(__DIR__.'/../../routes/cms-routes.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/cms-api.php');

        $router = $this->app['router'];
        $router->aliasMiddleware('cms', CmsMiddleware::class);
        $router->aliasMiddleware('cms_api', CmsApiAuth::class);

        Volt::mount(__DIR__.'/../../resources/views/livewire');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                NoerdCmsInstallCommand::class,
                InstallWebsiteBoilerplateCommand::class,
            ]);
        }
    }
}
