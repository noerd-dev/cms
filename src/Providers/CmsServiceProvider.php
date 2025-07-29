<?php

namespace Noerd\Cms\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;
use Noerd\Cms\Commands\NoerdCmsInstallCommand;
use Noerd\Cms\Middleware\CmsMiddleware;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'contents');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/content-routes.php');

        $router = $this->app['router'];
        $router->aliasMiddleware('cms', CmsMiddleware::class);

        Volt::mount(__DIR__ . '/../../resources/views/livewire');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                NoerdCmsInstallCommand::class,
            ]);
        }
    }
}
