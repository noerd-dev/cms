<?php

declare(strict_types=1);

use Livewire\Volt\Volt;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

test('page selection auto-fills empty name field', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $page = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => json_encode([
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]),
    ]);

    session(['selectedLanguage' => 'de']);

    Volt::test('navigation-detail')
        ->set('model', [
            'navigation_key' => 'test-nav',
            'name' => [], // Empty name field
            'page_id' => null,
        ])
        ->call('pageSelected', $page->id)
        ->assertSet('model.page_id', $page->id)
        ->assertSet('model.name', [
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]);
});

test('page selection does not overwrite existing name field', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $page = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
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

    Volt::test('navigation-detail')
        ->set('model', [
            'navigation_key' => 'test-nav',
            'name' => $existingName, // Pre-filled name field
            'page_id' => null,
        ])
        ->call('pageSelected', $page->id)
        ->assertSet('model.page_id', $page->id)
        ->assertSet('model.name', $existingName); // Should remain unchanged
});

test('page selection auto-fills when name field has only empty values', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $page = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => json_encode([
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]),
    ]);

    session(['selectedLanguage' => 'de']);

    Volt::test('navigation-detail')
        ->set('model', [
            'navigation_key' => 'test-nav',
            'name' => ['de' => '', 'en' => ''], // Empty string values
            'page_id' => null,
        ])
        ->call('pageSelected', $page->id)
        ->assertSet('model.page_id', $page->id)
        ->assertSet('model.name', [
            'de' => 'Test Seite',
            'en' => 'Test Page',
        ]);
});
