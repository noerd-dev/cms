<?php

namespace Noerd\Cms\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Noerd\Cms\Commands\InstallWebsiteBoilerplateCommand;
use Noerd\Cms\Commands\NoerdCmsInstallCommand;
use Noerd\Cms\Console\Commands\SyncFormTypesCommand;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Middleware\CmsApiAuth;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Models\Tenant;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register CollectionHelper as singleton for mockability in tests
        $this->app->singleton(CollectionHelper::class);

        // Register CMS PageElementService as fallback for Website namespace
        if (! $this->app->bound(\Noerd\Website\Services\PageElementService::class)) {
            $this->app->singleton(
                \Noerd\Website\Services\PageElementService::class,
                fn() => new \Noerd\Cms\Services\PageElementService(),
            );
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'cms');
        Livewire::addLocation(viewPath: __DIR__ . '/../../resources/views/components');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'cms');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/cms-routes.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/cms-api.php');

        // Merge configuration
        $this->mergeConfigFrom(__DIR__ . '/../../config/noerd_cms.php', 'noerd_cms');

        $router = $this->app['router'];
        $router->aliasMiddleware('cms_api', CmsApiAuth::class);

        // Register gate for CMS access
        Gate::define('canCms', function ($user) {
            $tenant = $user->selectedTenant();

            if (! $tenant) {
                return false;
            }

            $activeApps = $tenant->tenantApps->pluck('name')->toArray();

            return (bool) (array_intersect($activeApps, ['CMS']));
        });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                NoerdCmsInstallCommand::class,
                InstallWebsiteBoilerplateCommand::class,
                SyncFormTypesCommand::class,
            ]);
        }

        // Create default English language when a new tenant is created
        Tenant::created(function (Tenant $tenant): void {
            CmsLanguage::ensureDefaultLanguageForTenant($tenant->id);
        });
    }
}
