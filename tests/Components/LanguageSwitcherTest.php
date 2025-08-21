<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\Language;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

it('renders languages from database and sets default session', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    Language::create(['tenant_id' => $user->selected_tenant_id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);
    Language::create(['tenant_id' => $user->selected_tenant_id, 'code' => 'en', 'name' => 'English', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]);

    $component = Volt::test('language-switcher');

    $component->assertSet('languages.0.code', 'de');
    expect(session('selectedLanguage'))->toBe('de');
});

it('changes session language on click', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    Language::create(['tenant_id' => $user->selected_tenant_id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);
    Language::create(['tenant_id' => $user->selected_tenant_id, 'code' => 'en', 'name' => 'English', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]);

    $component = Volt::test('language-switcher');
    $component->call('setLanguage', 'en');
    expect(session('selectedLanguage'))->toBe('en');
});

it('does not render switcher if only one language exists', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    Language::create(['tenant_id' => $user->selected_tenant_id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);

    $html = Volt::test('language-switcher')->html();
    expect($html)->toBeString();
    // Should not contain the anchor for the single language code
    expect(str_contains($html, 'DE'))->toBeFalse();
});
