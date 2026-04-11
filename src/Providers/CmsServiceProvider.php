<?php

namespace Noerd\Cms\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Noerd\Cms\Commands\CmsUpdateCommand;
use Noerd\Cms\Commands\InstallWebsiteBoilerplateCommand;
use Noerd\Cms\Commands\NoerdCmsInstallCommand;
use Noerd\Cms\Console\Commands\ExportCollectionDefinitionsCommand;
use Noerd\Cms\Console\Commands\ImportCollectionDefinitionsCommand;
use Noerd\Cms\Console\Commands\SyncFormTypesCommand;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Middleware\CmsApiAuth;
use Noerd\Cms\Middleware\EnsureCollectionDefinitionsEnabled;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Navigation\CollectionsNavigationProvider;
use Noerd\Cms\Navigation\PageCollectionsNavigationProvider;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Repositories\YamlCollectionDefinitionRepository;
use Noerd\Models\Tenant;
use Noerd\Services\DynamicNavigationRegistry;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge configuration early so container bindings can read it during resolution.
        $this->mergeConfigFrom(__DIR__ . '/../../config/noerd_cms.php', 'noerd_cms');

        // Bind the collection definition repository based on the configured mode.
        // The mode now lives in the shared noerd.collections.* namespace so a
        // single toggle governs both CMS and Setup collection definitions.
        $this->app->singleton(CollectionDefinitionRepositoryContract::class, function ($app) {
            $mode = config('noerd.collections.mode', 'yaml');

            return match ($mode) {
                'database' => new DatabaseCollectionDefinitionRepository(),
                default => new YamlCollectionDefinitionRepository(
                    base_path(config('noerd_cms.collections.yaml_path', 'app-configs/cms/collections')),
                ),
            };
        });

        // Register CollectionHelper as singleton for mockability in tests
        $this->app->singleton(CollectionHelper::class, function ($app) {
            return new CollectionHelper($app->make(CollectionDefinitionRepositoryContract::class));
        });

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

        $router = $this->app['router'];
        $router->aliasMiddleware('cms_api', CmsApiAuth::class);
        $router->aliasMiddleware('cms.collections.ui', EnsureCollectionDefinitionsEnabled::class);

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
                CmsUpdateCommand::class,
                InstallWebsiteBoilerplateCommand::class,
                SyncFormTypesCommand::class,
                ImportCollectionDefinitionsCommand::class,
                ExportCollectionDefinitionsCommand::class,
            ]);
        }

        // Register dynamic navigation providers (resolved via container for constructor injection)
        $registry = $this->app->make(DynamicNavigationRegistry::class);
        $registry->register($this->app->make(CollectionsNavigationProvider::class));
        $registry->register($this->app->make(PageCollectionsNavigationProvider::class));

        // Create default English language when a new tenant is created
        Tenant::created(function (Tenant $tenant): void {
            CmsLanguage::ensureDefaultLanguageForTenant($tenant->id);
        });
    }
}
