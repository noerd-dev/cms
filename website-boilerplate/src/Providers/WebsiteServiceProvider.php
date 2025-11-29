<?php

namespace Noerd\Website\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;
use Noerd\Website\Controllers\WebsiteController;
use Noerd\Website\Middleware\WebsiteMiddleware;
use Noerd\Website\Models\Navigation;
use Noerd\Website\Services\PageElementService;
use Noerd\Website\Services\WebsiteService;

class WebsiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebsiteService::class, fn () => new WebsiteService);
        $this->app->singleton(PageElementService::class, fn () => new PageElementService);

        // Register reCAPTCHA configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/recaptcha.php', 'recaptcha');
    }

    public function boot(): void
    {
        // Register middleware alias BEFORE loading routes
        $router = $this->app['router'];
        $router->aliasMiddleware('website', WebsiteMiddleware::class);

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'website');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'website');

        // Register route loading after all providers have been registered
        $this->app->booted(function (): void {
            $this->registerCatchAllRoutes();
        });

        Volt::mount(__DIR__.'/../../resources/views/livewire');

        // Share website data to views
        view()->composer('*', function ($view): void {
            $tenantId = request()->attributes->get('tenant_id');
            if (! $tenantId) {
                return;
            }

            $service = app(WebsiteService::class);
            $lang = session('selectedLanguage', 'de');
            $hash = request()->query('hash');

            $globals = $service->getGlobals($tenantId);

            $navigationKeys = Navigation::where('tenant_id', $tenantId)
                ->distinct()
                ->pluck('navigation_key')
                ->toArray();

            $navigation = [];
            foreach ($navigationKeys as $key) {
                $navigation[mb_strtolower($key)] = $service->getNavigation($tenantId, $key, $lang, $hash);
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
