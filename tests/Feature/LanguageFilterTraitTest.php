<?php

declare(strict_types=1);

use Livewire\Volt\Volt;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('sets default language in session when not set', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create a default language for the tenant
    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    // Ensure session is empty
    session()->forget('selectedLanguage');

    // Visit pages list - this should set the session
    $this->get(route('cms.pages'))
        ->assertStatus(200);

    // Verify the session was set with the default language
    expect(session('selectedLanguage'))->toBe('de');
});

it('does not override existing language session', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create languages
    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'en',
        'name' => 'English',
        'is_active' => true,
        'is_default' => false,
    ]);

    // Set session to non-default language
    session(['selectedLanguage' => 'en']);

    // Visit pages list
    $this->get(route('cms.pages'))
        ->assertStatus(200);

    // Verify the session was NOT overwritten
    expect(session('selectedLanguage'))->toBe('en');
});

it('displays pages list without htmlspecialchars error when no session is set', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create a default language
    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    // Create a page with multilingual name
    Page::create([
        'tenant_id' => $tenant->id,
        'name' => ['de' => 'Testseite', 'en' => 'Test Page'],
        'slug' => ['de' => '/testseite', 'en' => '/test-page'],
    ]);

    // Ensure session is empty
    session()->forget('selectedLanguage');

    // Visit pages list - should not throw htmlspecialchars error
    $response = $this->get(route('cms.pages'));

    $response->assertStatus(200);
    $response->assertDontSee('htmlspecialchars()');
});

