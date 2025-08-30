<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\Tenant;
use Tests\TestCase;

class RoutePriorityTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_loads_cms_frontend_routes_last(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        // Find all generic slug routes
        $slugRoutes = $routes->filter(fn($route) => str_contains($route->uri(), '{slug?}'));

        // Get the last route in the application
        $lastRoute = $routes->last();

        // The generic website slug route should be the last route
        $this->assertStringContainsString('{slug?}/{slug2?}/{slug3?}', $lastRoute->uri());
        $this->assertEquals('website.slug', $lastRoute->getName());
        $this->assertStringContainsString('WebsiteController@slug', $lastRoute->getActionName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prioritizes_specific_routes_over_generic_slug_routes(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        // Find a specific route (like users or any other module route)
        $specificRoute = $routes->first(fn($route) => $route->uri() === 'users' || $route->uri() === 'vouchers');

        // Find the generic slug route
        $slugRoute = $routes->first(fn($route) => str_contains($route->uri(), '{slug?}/{slug2?}/{slug3?}'));

        $this->assertNotNull($specificRoute, 'A specific route should exist');
        $this->assertNotNull($slugRoute, 'The generic slug route should exist');

        // Get route positions in the collection
        $specificRouteIndex = $routes->search($specificRoute);
        $slugRouteIndex = $routes->search($slugRoute);

        // With our deferred loading, slug routes should now come AFTER specific routes
        $this->assertGreaterThan(
            $specificRouteIndex,
            $slugRouteIndex,
            'Generic slug routes should be registered after specific routes',
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function website_specific_route_is_accessible(): void
    {
        // Create a tenant for testing
        $tenant = Tenant::factory()->create();

        // Ensure there is at least one page and a cms_setting for this tenant
        $page = Page::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        CmsSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'homepage_page_id' => $page->id,
        ]);

        // Test that the specific website route works
        $response = $this->get("/index?hash={$tenant->hash}");

        // Should return 200 and render the website page
        $response->assertStatus(200);
    }
}
