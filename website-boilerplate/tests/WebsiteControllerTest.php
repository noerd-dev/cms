<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Website\Models\Page;
use Noerd\Website\Models\Tenant;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

it('renders the homepage configured in the cms settings via /index', function (): void {
    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Startseite', 'en' => 'Homepage'],
        'slug' => ['de' => '/', 'en' => '/'],
        'is_active' => true,
    ]);
    CmsSetting::factory()->create([
        'tenant_id' => $this->tenant->id,
        'homepage_page_id' => $page->id,
    ]);

    $response = $this->get('/index?uuid=' . $this->tenant->uuid);

    $response->assertOk();
    $response->assertViewIs('website::page');
    expect($response->viewData('page')->id)->toBe($page->id);
});

it('renders an active page by its slug', function (): void {
    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Über uns', 'en' => 'About us'],
        'slug' => ['de' => '/ueber-uns', 'en' => '/about-us'],
        'is_active' => true,
    ]);

    $response = $this->get('/ueber-uns?uuid=' . $this->tenant->uuid);

    $response->assertOk();
    $response->assertViewIs('website::page');
    expect($response->viewData('page')->id)->toBe($page->id);
});

it('resolves nested slugs across slug2 and slug3 segments', function (): void {
    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Webdesign', 'en' => 'Web design'],
        'slug' => ['de' => '/services/web/design', 'en' => '/services/web/design-en'],
        'is_active' => true,
    ]);

    $response = $this->get('/services/web/design?uuid=' . $this->tenant->uuid);

    $response->assertOk();
    $response->assertViewIs('website::page');
    expect($response->viewData('page')->id)->toBe($page->id);
});

it('returns 404 for an inactive page', function (): void {
    Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Geheim', 'en' => 'Secret'],
        'slug' => ['de' => '/geheim', 'en' => '/secret'],
        'is_active' => false,
    ]);

    $response = $this->get('/geheim?uuid=' . $this->tenant->uuid);

    $response->assertNotFound();
});

it('returns 404 for an unknown slug', function (): void {
    $response = $this->get('/gibt-es-nicht?uuid=' . $this->tenant->uuid);

    $response->assertNotFound();
});

it('does not serve a page belonging to another tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    Page::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => ['de' => 'Fremde Seite', 'en' => 'Foreign page'],
        'slug' => ['de' => '/fremde-seite', 'en' => '/foreign-page'],
        'is_active' => true,
    ]);

    $response = $this->get('/fremde-seite?uuid=' . $this->tenant->uuid);

    $response->assertNotFound();
});

describe('route priority', function (): void {
    it('loads cms frontend routes last', function (): void {
        $slugRoute = collect(Route::getRoutes()->getRoutes())
            ->first(fn($route) => $route->getName() === 'website.slug');

        $this->assertNotNull($slugRoute, 'The generic slug route should exist');
        $this->assertStringContainsString('{slug?}/{slug2?}/{slug3?}', $slugRoute->uri());

        // A known static route must not be swallowed by the slug catch-all:
        // /noerd-apps still resolves to the noerd core route, not website.slug.
        $matched = Route::getRoutes()->match(Request::create('/noerd-apps', 'GET'));
        $this->assertEquals('noerd.apps', $matched->getName());
    });
});
