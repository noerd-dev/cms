<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Website\Models\Navigation;
use Noerd\Website\Models\Page;
use Noerd\Website\Models\Tenant;
use Noerd\Website\Services\WebsiteService;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new WebsiteService();
    $this->tenant = Tenant::factory()->create();
});

it('creates navigation href from page slug', function (): void {
    // Create a page with multilingual slugs
    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Startseite', 'en' => 'Homepage'],
        'slug' => json_encode(['de' => '/startseite', 'en' => '/homepage']),
        'is_active' => true,
    ]);

    // Create navigation item pointing to this page
    Navigation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'main',
        'name' => json_encode(['de' => 'Start', 'en' => 'Home']),
        'page_id' => $page->id,
        'link' => null,
        'new_tab' => false,
    ]);

    // Test German navigation
    $navigation = $this->service->getNavigation($this->tenant->id, 'main', 'de', $this->tenant->uuid);

    $this->assertCount(1, $navigation);
    $this->assertEquals('Start', $navigation[0]['name']);
    $this->assertEquals('/startseite', $navigation[0]['href']);
    $this->assertFalse($navigation[0]['new_tab']);

    // Test English navigation
    $navigation = $this->service->getNavigation($this->tenant->id, 'main', 'en', $this->tenant->uuid);

    $this->assertCount(1, $navigation);
    $this->assertEquals('Home', $navigation[0]['name']);
    $this->assertEquals('/homepage', $navigation[0]['href']);
});

it('falls back to direct link when no page id', function (): void {
    // Create navigation item with direct link
    Navigation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'main',
        'name' => json_encode(['de' => 'Externe Seite']),
        'page_id' => null,
        'link' => 'https://example.com',
        'new_tab' => true,
    ]);

    $navigation = $this->service->getNavigation($this->tenant->id, 'main', 'de', $this->tenant->uuid);

    $this->assertCount(1, $navigation);
    $this->assertEquals('Externe Seite', $navigation[0]['name']);
    $this->assertEquals('https://example.com', $navigation[0]['href']);
    $this->assertTrue($navigation[0]['new_tab']);
});

it('falls back to route when page has no slug', function (): void {
    // Create a page without slug
    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Seite ohne Slug'],
        'slug' => null, // No slug
        'is_active' => true,
    ]);

    // Create navigation item pointing to this page
    Navigation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'main',
        'name' => json_encode(['de' => 'Seite ohne Slug']),
        'page_id' => $page->id,
        'link' => null,
        'new_tab' => false,
    ]);

    $result = $this->service->getNavigation($this->tenant->id, 'main', 'de', $this->tenant->uuid);

    $this->assertCount(1, $result);
    $this->assertEquals('Seite ohne Slug', $result[0]['name']);
    $this->assertStringContainsString('index', $result[0]['href']);
    $this->assertStringContainsString($this->tenant->uuid, $result[0]['href']);
    $this->assertStringContainsString("page={$page->id}", $result[0]['href']);
});

it('handles language fallback for missing slug', function (): void {
    // Create a page with only German slug
    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Nur Deutsch'],
        'slug' => json_encode(['de' => '/nur-deutsch']),
        'is_active' => true,
    ]);

    Navigation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'main',
        'name' => json_encode(['de' => 'Nur Deutsch']),
        'page_id' => $page->id,
        'link' => null,
        'new_tab' => false,
    ]);

    // Test with English language - should fall back to German slug
    $navigation = $this->service->getNavigation($this->tenant->id, 'main', 'en', $this->tenant->uuid);

    $this->assertCount(1, $navigation);
    $this->assertEquals('/nur-deutsch', $navigation[0]['href']); // Falls back to German
});
