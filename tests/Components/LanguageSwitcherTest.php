<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Noerd\Models\Language;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('renders languages from database and sets default session', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Language::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);
    Language::create(['tenant_id' => $tenant->id, 'code' => 'en', 'name' => 'English', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]);

    $component = Volt::test('language-switcher');

    $component->assertSet('languages.0.code', 'de');
    expect(session('selectedLanguage'))->toBe('de');
});

it('changes session language on click', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Language::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);
    Language::create(['tenant_id' => $tenant->id, 'code' => 'en', 'name' => 'English', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]);

    $component = Volt::test('language-switcher');
    $component->call('setLanguage', 'en');
    expect(session('selectedLanguage'))->toBe('en');
});

it('does not render switcher if only one language exists', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Language::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);

    $html = Volt::test('language-switcher')->html();
    expect($html)->toBeString();
    // Should not contain the anchor for the single language code
    expect(str_contains($html, 'DE'))->toBeFalse();
});
