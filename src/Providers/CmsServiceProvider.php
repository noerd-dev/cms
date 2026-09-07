<?php

declare(strict_types=1);

namespace Noerd\Cms\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Noerd\Cms\Commands\CmsInstallCommand;
use Noerd\Cms\Commands\CmsUpdateCommand;
use Noerd\Cms\Commands\InstallWebsiteBoilerplateCommand;
use Noerd\Cms\Commands\SyncFormTypesCommand;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Http\Middleware\CmsApiAuth;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Navigation\CollectionsNavigationProvider;
use Noerd\Cms\Navigation\PageCollectionsNavigationProvider;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Repositories\ElementAwareCollectionDefinitionRepository;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Support\CollectionSelectOptions;
use Noerd\Models\Tenant;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\ComponentAccessGuard;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Support\RelationFieldDefinition;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge configuration early so container bindings can read it during resolution.
        $this->mergeConfigFrom(__DIR__ . '/../../config/noerd_cms.php', 'noerd_cms');

        // Decorated so element collections resolve their schema from the owning
        // DB collection's stored element_fields.
        $this->app->singleton(CollectionDefinitionRepositoryContract::class, fn($app) => new ElementAwareCollectionDefinitionRepository(
            new DatabaseCollectionDefinitionRepository(),
            $app->make(ElementCollectionService::class),
        ));

        // Register CollectionHelper as singleton for mockability in tests
        $this->app->singleton(CollectionHelper::class, fn($app) => new CollectionHelper($app->make(CollectionDefinitionRepositoryContract::class)));

        // Soft integration point: the website module resolves its
        // PageElementService from the container; when website is not installed
        // (or has not bound it yet), the CMS implementation answers instead.
        // Deliberately a plain container KEY — never a class load — so CMS has
        // no dependency on the website module.
        if (! $this->app->bound('Noerd\Website\Services\PageElementService')) {
            $this->app->singleton(
                'Noerd\Website\Services\PageElementService',
                fn() => new \Noerd\Cms\Services\PageElementService(),
            );
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'cms');
        Livewire::addNamespace('cms', viewPath: __DIR__ . '/../../resources/views/components');
        Livewire::addLocation(viewPath: __DIR__ . '/../../resources/views/components');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/cms-routes.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/cms-api.php');

        $router = $this->app['router'];
        $router->aliasMiddleware('cms_api', CmsApiAuth::class);

        $this->publishes([
            __DIR__ . '/../../config/noerd_cms.php' => config_path('noerd_cms.php'),
        ], 'cms-config');

        // Tenant-wide configuration screens are admin-only. The guard runs on
        // every mount (route, noerdModal event, component-page), so the routes
        // need no extra middleware — and deliberately not `setup`, which would
        // switch the selected app away from CMS.
        ComponentAccessGuard::registerAdminComponents([
            'cms::settings-page',
            'cms::languages-list',
            'cms::language-detail',
            'cms::collection-definitions-list',
            'cms::collection-definition-detail',
        ]);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CmsInstallCommand::class,
                CmsUpdateCommand::class,
                InstallWebsiteBoilerplateCommand::class,
                SyncFormTypesCommand::class,
            ]);
        }

        // Register dynamic navigation providers (resolved via container for constructor injection)
        $registry = $this->app->make(DynamicNavigationRegistry::class);
        $registry->register($this->app->make(CollectionsNavigationProvider::class));
        $registry->register($this->app->make(PageCollectionsNavigationProvider::class));

        $fieldTypeRegistry = $this->app->make(FieldTypeRegistry::class);
        $relationFieldRegistry = $this->app->make(RelationFieldRegistry::class);
        // Both selects resolve their options here (once per render, never in
        // the template) and render through the active theme's select element.
        $fieldTypeRegistry->register('collection-select', FieldTypeDefinition::include(
            'cms::components.forms.input-collection-select',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                'field' => $field + ['options' => CollectionSelectOptions::collections($field['required_fields'] ?? [])],
            ],
        ));

        // Dynamic page select used by the CMS settings page (homepage picker).
        $fieldTypeRegistry->register('homepage-select', FieldTypeDefinition::include(
            'cms::components.forms.input-homepage-select',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                'field' => $field + ['options' => CollectionSelectOptions::pages(), 'placeholder' => 'None selected'],
            ],
        ));

        $fieldTypeRegistry->register('element-collection', FieldTypeDefinition::livewire(
            'cms::element-collection-field',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                // A page-element editor exposes an `elementKey` property; a collection
                // entry editor (page-detail, element-collection-row-detail) does not.
                'ownerType' => (is_object($component) && property_exists($component, 'elementKey')) ? 'element_page' : 'page',
                'ownerId' => $modelId,
                'fieldName' => str_replace('detailData.', '', (string) ($field['name'] ?? '')),
                'label' => (string) ($field['label'] ?? ''),
                'rowFields' => $field['fields'] ?? [],
            ],
            keyResolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): string => 'element-' . str_replace('detailData.', '', (string) ($field['name'] ?? '')) . '-' . ($modelId ?? 'new'),
        ));

        $relationFieldRegistry->register('pageRelation', RelationFieldDefinition::model(
            listComponent: 'cms::pages-list',
            detailComponent: 'cms::page-detail',
            detailRoute: 'cms.page.detail',
            modelClass: Page::class,
            titleResolver: fn(Page $page): string => RelationFieldDefinition::normalizeDisplayValue($page->name),
        ));
        $relationFieldRegistry->register('authorRelation', RelationFieldDefinition::model(
            listComponent: 'cms::authors-list',
            detailComponent: 'cms::author-detail',
            detailRoute: 'cms.author.detail',
            modelClass: Author::class,
            titleResolver: 'name',
        ));

        // Create default English language when a new tenant is created
        Tenant::created(function (Tenant $tenant): void {
            CmsLanguage::ensureDefaultLanguageForTenant($tenant->id);
        });
    }
}
