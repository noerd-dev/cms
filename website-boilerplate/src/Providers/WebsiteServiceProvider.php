<?php

declare(strict_types=1);

namespace Noerd\Website\Providers;

use Illuminate\View\View;
use Livewire\Livewire;
use Noerd\Providers\NoerdModuleServiceProvider;
use Noerd\Website\Commands\WebsiteUpdateCommand;
use Noerd\Website\Controllers\WebsiteController;
use Noerd\Website\Middleware\WebsiteMiddleware;
use Noerd\Website\Services\PageElementService;
use Noerd\Website\Services\WebsiteService;

/**
 * Views, the `website::` Livewire namespace, translations and
 * routes/website-routes.php are loaded by the base class from the module key.
 */
class WebsiteServiceProvider extends NoerdModuleServiceProvider
{
    protected string $moduleKey = 'website';

    protected array $commands = [
        WebsiteUpdateCommand::class,
    ];

    /**
     * The config is merged at runtime only — never published — so it is
     * merged here instead of through $configs (which would publish it).
     */
    protected function registerModule(): void
    {
        // Scoped: the service memoizes globals and navigation for ONE request.
        $this->app->scoped(WebsiteService::class, fn() => new WebsiteService());
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

        // Globals and navigation for the layouts (and the page views that
        // render them) — not for every view of the application; the service
        // memoizes them, so several layouts of one request query once.
        view()->composer([
            'website::components.layouts.*',
            'website::page',
            'website::article',
            'website::articles',
            'website::welcome',
        ], function (View $view): void {
            $tenantId = request()->attributes->get('tenant_id');
            if (! $tenantId) {
                return;
            }

            $service = app(WebsiteService::class);
            $language = (string) \Noerd\Website\Support\WebsiteLanguage::current();
            $uuid = request()->query('uuid');

            $view->with('globals', $service->getGlobals((int) $tenantId))
                ->with('navigation', $service->getNavigations((int) $tenantId, $language, is_string($uuid) ? $uuid : null));
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
