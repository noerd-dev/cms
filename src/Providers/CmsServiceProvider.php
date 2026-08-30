<?php

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
use Noerd\Models\Tenant;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\RelationFieldRegistry;
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
        $fieldTypeRegistry->register('collection-select', FieldTypeDefinition::include(
            'cms::components.forms.input-collection-select',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));

        // Dynamic page select used by the CMS settings page (homepage picker).
        $fieldTypeRegistry->register('homepage-select', FieldTypeDefinition::include(
            'cms::components.forms.input-homepage-select',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));

        $fieldTypeRegistry->register('element-collection', FieldTypeDefinition::livewire(
            'cms::element-collection-field',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                // A page-element editor exposes an `elementPage` property; a collection
                // entry editor (page-detail) does not.
                'ownerType' => (is_object($component) && property_exists($component, 'elementPage')) ? 'element_page' : 'page',
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
            modelClass: Page::class,
            titleResolver: fn(Page $page): string => RelationFieldDefinition::normalizeDisplayValue($page->name),
        ));
        $relationFieldRegistry->register('authorRelation', RelationFieldDefinition::model(
            listComponent: 'cms::authors-list',
            detailComponent: 'cms::author-detail',
            modelClass: Author::class,
            titleResolver: 'name',
        ));

        // Create default English language when a new tenant is created
        Tenant::created(function (Tenant $tenant): void {
            CmsLanguage::ensureDefaultLanguageForTenant($tenant->id);
        });
    }
}
