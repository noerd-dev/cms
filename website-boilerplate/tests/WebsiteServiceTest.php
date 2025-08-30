<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Website\Models\Navigation;
use Noerd\Website\Models\Page;
use Noerd\Website\Models\Tenant;
use Noerd\Website\Services\WebsiteService;
use Tests\TestCase;

class WebsiteServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebsiteService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WebsiteService();
        $this->tenant = Tenant::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_navigation_href_from_page_slug(): void
    {
        // Create a page with multilingual slugs
        $page = Page::create([
            'tenant_id' => $this->tenant->id,
            'name' => json_encode(['de' => 'Startseite', 'en' => 'Homepage']),
            'slug' => json_encode(['de' => '/startseite', 'en' => '/homepage']),
            'is_active' => true,
        ]);

        // Create navigation item pointing to this page
        Navigation::create([
            'tenant_id' => $this->tenant->id,
            'navigation_key' => 'main',
            'name' => json_encode(['de' => 'Start', 'en' => 'Home']),
            'page_id' => $page->id,
            'link' => null,
            'new_tab' => false,
        ]);

        // Test German navigation
        $navigation = $this->service->getNavigation($this->tenant->id, 'main', 'de', $this->tenant->hash);

        $this->assertCount(1, $navigation);
        $this->assertEquals('Start', $navigation[0]['label']);
        $this->assertEquals('/startseite', $navigation[0]['href']);
        $this->assertFalse($navigation[0]['new_tab']);

        // Test English navigation
        $navigation = $this->service->getNavigation($this->tenant->id, 'main', 'en', $this->tenant->hash);

        $this->assertCount(1, $navigation);
        $this->assertEquals('Home', $navigation[0]['label']);
        $this->assertEquals('/homepage', $navigation[0]['href']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_falls_back_to_direct_link_when_no_page_id(): void
    {
        // Create navigation item with direct link
        Navigation::create([
            'tenant_id' => $this->tenant->id,
            'navigation_key' => 'main',
            'name' => json_encode(['de' => 'Externe Seite']),
            'page_id' => null,
            'link' => 'https://example.com',
            'new_tab' => true,
        ]);

        $navigation = $this->service->getNavigation($this->tenant->id, 'main', 'de', $this->tenant->hash);

        $this->assertCount(1, $navigation);
        $this->assertEquals('Externe Seite', $navigation[0]['label']);
        $this->assertEquals('https://example.com', $navigation[0]['href']);
        $this->assertTrue($navigation[0]['new_tab']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_falls_back_to_route_when_page_has_no_slug(): void
    {
        // Create a page without slug
        $page = Page::create([
            'tenant_id' => $this->tenant->id,
            'name' => json_encode(['de' => 'Seite ohne Slug']),
            'slug' => null, // No slug
            'is_active' => true,
        ]);

        // Create navigation item pointing to this page
        Navigation::create([
            'tenant_id' => $this->tenant->id,
            'navigation_key' => 'main',
            'name' => json_encode(['de' => 'Seite ohne Slug']),
            'page_id' => $page->id,
            'link' => null,
            'new_tab' => false,
        ]);

        $result = $this->service->getNavigation($this->tenant->id, 'main', 'de', $this->tenant->hash);

        $this->assertCount(1, $result);
        $this->assertEquals('Seite ohne Slug', $result[0]['label']);
        $this->assertStringContainsString('index', $result[0]['href']);
        $this->assertStringContainsString($this->tenant->hash, $result[0]['href']);
        $this->assertStringContainsString("page={$page->id}", $result[0]['href']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_language_fallback_for_missing_slug(): void
    {
        // Create a page with only German slug
        $page = Page::create([
            'tenant_id' => $this->tenant->id,
            'name' => json_encode(['de' => 'Nur Deutsch']),
            'slug' => json_encode(['de' => '/nur-deutsch']),
            'is_active' => true,
        ]);

        Navigation::create([
            'tenant_id' => $this->tenant->id,
            'navigation_key' => 'main',
            'name' => json_encode(['de' => 'Nur Deutsch']),
            'page_id' => $page->id,
            'link' => null,
            'new_tab' => false,
        ]);

        // Test with English language - should fall back to German slug
        $navigation = $this->service->getNavigation($this->tenant->id, 'main', 'en', $this->tenant->hash);

        $this->assertCount(1, $navigation);
        $this->assertEquals('/nur-deutsch', $navigation[0]['href']); // Falls back to German
    }
}
