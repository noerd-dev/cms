<?php

declare(strict_types=1);

namespace Noerd\Cms\Providers;

use Illuminate\Support\Facades\Route;
use Noerd\Cms\Commands\CmsInstallCommand;
use Noerd\Cms\Commands\CmsUpdateCommand;
use Noerd\Cms\Commands\InstallWebsiteBoilerplateCommand;
use Noerd\Cms\Commands\SyncFormTypesCommand;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Http\Controllers\AssetController;
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
use Noerd\Cms\Support\ContentTypeRegistry;
use Noerd\Models\Tenant;
use Noerd\Providers\NoerdModuleServiceProvider;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Support\ComponentAccessGuard;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Support\RelationFieldDefinition;

class CmsServiceProvider extends NoerdModuleServiceProvider
{
    protected string $moduleKey = 'cms';

    protected array $commands = [
        CmsInstallCommand::class,
        CmsUpdateCommand::class,
        InstallWebsiteBoilerplateCommand::class,
        SyncFormTypesCommand::class,
    ];

    /** Merged before registerModule(), so the container bindings can read it. */
    protected array $configs = ['noerd_cms'];

    protected function registerModule(): void
    {
        // Decorated so element collections resolve their schema from the owning
        // DB collection's stored element_fields.
        $this->app->singleton(CollectionDefinitionRepositoryContract::class, fn($app) => new ElementAwareCollectionDefinitionRepository(
            new DatabaseCollectionDefinitionRepository(),
            $app->make(ElementCollectionService::class),
        ));

        $this->app->singleton(ContentTypeRegistry::class);

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

    /**
     * @return list<string>
     */
    protected function routeFiles(): array
    {
        return [
            $this->modulePath('routes/cms-routes.php'),
            $this->modulePath('routes/cms-api.php'),
        ];
    }

    /**
     * @return array<string, FieldTypeDefinition>
     */
    protected function fieldTypes(): array
    {
        return [
            // Both selects resolve their options here (once per render, never in
            // the template) and render through the active theme's select element.
            'collection-select' => FieldTypeDefinition::include(
                'cms::components.forms.input-collection-select',
                resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                    'field' => $field + ['options' => CollectionSelectOptions::collections($field['required_fields'] ?? [])],
                ],
            ),
            // Dynamic page select used by the CMS settings page (homepage picker).
            'homepage-select' => FieldTypeDefinition::include(
                'cms::components.forms.input-homepage-select',
                resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                    'field' => $field + ['options' => CollectionSelectOptions::pages(), 'placeholder' => 'None selected'],
                ],
            ),
            'element-collection' => FieldTypeDefinition::livewire(
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
            ),
        ];
    }

    /**
     * @return array<string, RelationFieldDefinition>
     */
    protected function relationFields(): array
    {
        return [
            'pageRelation' => RelationFieldDefinition::model(
                listComponent: 'cms::pages-list',
                detailComponent: 'cms::page-detail',
                detailRoute: 'cms.page.detail',
                modelClass: Page::class,
                titleResolver: fn(Page $page): string => RelationFieldDefinition::normalizeDisplayValue($page->name),
            ),
            'authorRelation' => RelationFieldDefinition::model(
                listComponent: 'cms::authors-list',
                detailComponent: 'cms::author-detail',
                detailRoute: 'cms.author.detail',
                modelClass: Author::class,
                titleResolver: 'name',
            ),
        ];
    }

    protected function bootModule(): void
    {
        $this->registerAssetRoute();

        $router = $this->app['router'];
        $router->aliasMiddleware('cms_api', CmsApiAuth::class);

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

        // Register dynamic navigation providers (resolved via container for constructor injection)
        $registry = $this->app->make(DynamicNavigationRegistry::class);
        $registry->register($this->app->make(CollectionsNavigationProvider::class));
        $registry->register($this->app->make(PageCollectionsNavigationProvider::class));

        $this->app->make(ContentTypeRegistry::class)->register(
            key: Page::contentType(),
            label: 'Website Page',
            modelClass: Page::class,
            detailRoute: 'cms.page.detail',
            detailComponent: 'cms::page-detail',
        );

        // Create default English language when a new tenant is created
        Tenant::created(function (Tenant $tenant): void {
            CmsLanguage::ensureDefaultLanguageForTenant($tenant->id);
        });
    }

    /**
     * The prebuilt document editor bundle is served straight from the package
     * (dist/build), nothing is copied into public/. No middleware: a static
     * asset needs neither a session nor CSRF.
     */
    private function registerAssetRoute(): void
    {
        Route::get('/cms-assets/{file}', AssetController::class)
            ->where('file', '[A-Za-z0-9._-]+')
            ->name('cms.asset');
    }
}
