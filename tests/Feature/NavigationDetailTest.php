<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);
});

it('allows storing a navigation without page or link for parent items', function (): void {
    $tenant = $this->tenant;

    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['de' => 'Start', 'en' => 'Start'])
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'page_id' => null,
        'link' => null,
    ]);
});

it('persists parent_id when assigning a parent to an existing navigation', function (): void {
    $tenant = $this->tenant;

    $parent = Navigation::factory()->create([
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'name' => ['de' => 'Hauptmenü', 'en' => 'Main'],
        'parent_id' => null,
    ]);

    $item = Navigation::factory()->create([
        'tenant_id' => $tenant->id,
        'navigation_key' => 'ITEM',
        'name' => ['de' => 'Punkt', 'en' => 'Item'],
        'parent_id' => null,
    ]);

    Livewire::test('cms::navigation-detail', ['modelId' => $item->id])
        ->set('detailData.parent_id', $parent->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'id' => $item->id,
        'parent_id' => $parent->id,
    ]);
});

it('stores a new sub navigation and inherits the parent navigation_key', function (): void {
    $tenant = $this->tenant;

    $parent = Navigation::factory()->create([
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'name' => ['de' => 'Hauptmenü', 'en' => 'Main'],
        'parent_id' => null,
    ]);

    Livewire::test('cms::navigation-detail')
        ->set('detailData.name', ['de' => 'Unterpunkt', 'en' => 'Subitem'])
        ->set('detailData.parent_id', $parent->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'tenant_id' => $tenant->id,
        'parent_id' => $parent->id,
        'navigation_key' => 'MAIN',
    ]);
});

it('stores a navigation with page and clears link', function (): void {
    $tenant = $this->tenant;

    $page = Page::factory()->create(['tenant_id' => $tenant->id, 'name' => ['de' => 'Seite', 'en' => 'Page']]);

    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['de' => 'Start', 'en' => 'Start'])
        ->set('detailData.link', 'https://example.com')
        ->call('pageSelected', $page->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'page_id' => $page->id,
        'link' => null,
    ]);
});

it('stores a navigation with link and clears page', function (): void {
    $tenant = $this->tenant;

    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['de' => 'Kontakt', 'en' => 'Contact'])
        ->set('detailData.link', 'https://example.com/contact')
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'link' => 'https://example.com/contact',
        'page_id' => null,
    ]);
});

it('normalizes new_tab default and stores 0 when not set', function (): void {
    $tenant = $this->tenant;

    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['de' => 'Blog', 'en' => 'Blog'])
        ->set('detailData.link', 'https://example.com/blog')
        ->call('store')
        ->assertHasNoErrors();

    $navigation = Navigation::where('tenant_id', $tenant->id)
        ->where('navigation_key', 'MAIN')
        ->first();

    expect($navigation)->not->toBeNull();
    expect((int) $navigation->new_tab)->toBe(0);
});


describe('page selection', function (): void {
    test('page selection auto-fills empty name field', function (array $emptyName): void {
        $page = Page::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => [
                'de' => 'Test Seite',
                'en' => 'Test Page',
            ],
        ]);

        session(['selectedLanguage' => 'de']);

        Livewire::test('cms::navigation-detail')
            ->set('detailData', [
                'navigation_key' => 'test-nav',
                'name' => $emptyName,
                'page_id' => null,
            ])
            ->call('pageSelected', $page->id)
            ->assertSet('detailData.page_id', $page->id)
            ->assertSet('detailData.name', [
                'de' => 'Test Seite',
                'en' => 'Test Page',
            ]);
    })->with([
        'missing values' => [[]],
        'empty string values' => [['de' => '', 'en' => '']],
    ]);

    test('page selection does not overwrite existing name field', function (): void {
        $page = Page::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => [
                'de' => 'Test Seite',
                'en' => 'Test Page',
            ],
        ]);

        $existingName = [
            'de' => 'Bereits vorhandener Name',
            'en' => 'Existing Name',
        ];

        session(['selectedLanguage' => 'de']);

        Livewire::test('cms::navigation-detail')
            ->set('detailData', [
                'navigation_key' => 'test-nav',
                'name' => $existingName, // Pre-filled name field
                'page_id' => null,
            ])
            ->call('pageSelected', $page->id)
            ->assertSet('detailData.page_id', $page->id)
            ->assertSet('detailData.name', $existingName); // Should remain unchanged
    });
});
