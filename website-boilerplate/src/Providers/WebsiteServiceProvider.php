<?php

declare(strict_types=1);

namespace Noerd\Website\Providers;

use Livewire\Livewire;
use Noerd\Providers\NoerdModuleServiceProvider;
use Noerd\Website\Controllers\WebsiteController;
use Noerd\Website\Middleware\WebsiteMiddleware;
use Noerd\Website\Models\Navigation;
use Noerd\Website\Services\PageElementService;
use Noerd\Website\Services\WebsiteService;

/**
 * Views, the `website::` Livewire namespace, translations and
 * routes/website-routes.php are loaded by the base class from the module key.
 */
class WebsiteServiceProvider extends NoerdModuleServiceProvider
{
    protected string $moduleKey = 'website';

    /**
     * The config is merged at runtime only — never published — so it is
     * merged here instead of through $configs (which would publish it).
     */
    protected function registerModule(): void
    {
        $this->app->singleton(WebsiteService::class, fn() => new WebsiteService());
        $this->app->singleton(PageElementService::class, fn() => new PageElementService());

        $this->mergeConfigFrom($this->modulePath('config/website.php'), 'website');

        // The middleware alias exists before the route files are loaded.
        $this->app['router']->aliasMiddleware('website', WebsiteMiddleware::class);
    }

    protected function bootModule(): void
    {
        // Livewire updates (the contact form) work in the visitor's tenant too.
        Livewire::addPersistentMiddleware(WebsiteMiddleware::class);

        // Register route loading after all providers have been registered
        $this->app->booted(function (): void {
            $this->registerCatchAllRoutes();
        });

        // Share website data to views
        view()->composer('*', function ($view): void {
            $tenantId = request()->attributes->get('tenant_id');
            if (! $tenantId) {
                return;
            }

            $service = app(WebsiteService::class);
            $lang = session('selectedLanguage', 'de');
            $uuid = request()->query('uuid');

            $globals = $service->getGlobals($tenantId);

            $navigationKeys = Navigation::where('tenant_id', $tenantId)
                ->distinct()
                ->pluck('navigation_key')
                ->toArray();

            $navigation = [];
            foreach ($navigationKeys as $key) {
                $navigation[mb_strtolower($key)] = $service->getNavigation($tenantId, $key, $lang, $uuid);
            }

            $view->with('globals', $globals)
                ->with('navigation', $navigation);
        });
    }

    /**
     * Register catch-all routes after all other providers have been booted
     * This ensures our generic slug routes don't override other module routes
     */
    private function registerCatchAllRoutes(): void
    {
        $router = app('router');

        // Register catch-all route LAST to ensure lowest priority
        $router->middleware(['web', WebsiteMiddleware::class])
            ->get('/{slug?}/{slug2?}/{slug3?}', [WebsiteController::class, 'slug'])
            ->name('website.slug');
    }
}
