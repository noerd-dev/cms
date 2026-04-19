<?php

declare(strict_types=1);


use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

test('page selection auto-fills empty name field', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => json_encode([
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]),
    ]);

    session(['selectedLanguage' => 'de']);

    Livewire::test('cms::navigation-detail')
        ->set('detailData', [
            'navigation_key' => 'test-nav',
            'name' => [], // Empty name field
            'page_id' => null,
        ])
        ->call('pageSelected', $page->id)
        ->assertSet('detailData.page_id', $page->id)
        ->assertSet('detailData.name', [
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]);
});

test('page selection does not overwrite existing name field', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => json_encode([
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]),
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

test('page selection auto-fills when name field has only empty values', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => json_encode([
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]),
    ]);

    session(['selectedLanguage' => 'de']);

    Livewire::test('cms::navigation-detail')
        ->set('detailData', [
            'navigation_key' => 'test-nav',
            'name' => ['de' => '', 'en' => ''], // Empty string values
            'page_id' => null,
        ])
        ->call('pageSelected', $page->id)
        ->assertSet('detailData.page_id', $page->id)
        ->assertSet('detailData.name', [
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]);
});
