<?php

namespace Noerd\Cms\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;
use Noerd\Cms\Commands\InstallWebsiteBoilerplateCommand;
use Noerd\Cms\Commands\NoerdCmsInstallCommand;
use Noerd\Cms\Console\Commands\SyncFormTypesCommand;
use Noerd\Cms\Middleware\CmsApiAuth;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Noerd\Models\Tenant;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'cms');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'cms');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/cms-routes.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/cms-api.php');

        // Merge configuration
        $this->mergeConfigFrom(__DIR__ . '/../../config/noerd_cms.php', 'noerd_cms');

        $router = $this->app['router'];
        $router->aliasMiddleware('cms_api', CmsApiAuth::class);

        Volt::mount(__DIR__ . '/../../resources/views/livewire');

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
