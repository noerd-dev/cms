<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Support\TenantContext;
use Noerd\Website\Models\ElementPage;
use Noerd\Website\Models\Page;
use Noerd\Website\Models\Tenant;
use Noerd\Website\Services\PageElementService;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // The component mapping globs app-modules/*/resources/views/livewire/elements
    // — a directory no module ships — so out of the box the map is empty and the
    // page view renders its "Element component not found" fallback. The
    // supported hook for custom element locations is the CMS_PAGE_ELEMENTS_PATH
    // env variable; point it at the module's own shipped elements so the real
    // element component renders.
    $this->previousElementsPath = $_SERVER['CMS_PAGE_ELEMENTS_PATH'] ?? null;

    $path = 'app-modules/website/resources/views/components/elements';
    $_ENV['CMS_PAGE_ELEMENTS_PATH'] = $path;
    $_SERVER['CMS_PAGE_ELEMENTS_PATH'] = $path;
    putenv('CMS_PAGE_ELEMENTS_PATH=' . $path);
});

afterEach(function (): void {
    if ($this->previousElementsPath === null) {
        unset($_ENV['CMS_PAGE_ELEMENTS_PATH'], $_SERVER['CMS_PAGE_ELEMENTS_PATH']);
        putenv('CMS_PAGE_ELEMENTS_PATH');
    } else {
        $_ENV['CMS_PAGE_ELEMENTS_PATH'] = $this->previousElementsPath;
        $_SERVER['CMS_PAGE_ELEMENTS_PATH'] = $this->previousElementsPath;
        putenv('CMS_PAGE_ELEMENTS_PATH=' . $this->previousElementsPath);
    }
});

it('renders a page element end to end through the element component', function (): void {
    $tenant = Tenant::factory()->create();
    // The tenant's website runs German as its default language (without any
    // language the CMS falls back to `en`, CmsLanguageCodes::FALLBACK).
    \Noerd\Website\Models\Language::factory()->create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => true]);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => ['de' => 'Elementseite', 'en' => 'Element page'],
        'slug' => ['de' => '/element-render', 'en' => '/element-render'],
        'is_active' => true,
    ]);

    ElementPage::create([
        'content_id' => $page->content_id,
        'element_key' => 'text_block_1_column',
        'sort' => 1,
        'data' => json_encode([
            'text' => ['de' => 'Hallo Element Welt', 'en' => 'Hello element world'],
        ]),
    ]);

    // The service localizes the stored element data for the selected language —
    // called directly, in the page's tenant as the website middleware would.
    $elements = TenantContext::run($tenant->id, fn(): array => app(PageElementService::class)->processPageElements($page->fresh(), 'de'));

    expect($elements)->toHaveCount(1);
    expect($elements[0]['key'])->toBe('text_block_1_column');
    expect($elements[0]['data']->text)->toBe('Hallo Element Welt');

    // Full round trip: the page route renders the element's Livewire component.
    $response = $this->get('/element-render?uuid=' . $tenant->uuid);

    $response->assertOk();
    $response->assertSee('Hallo Element Welt');
    $response->assertDontSee('Element component not found');
});
