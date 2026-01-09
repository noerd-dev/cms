<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('renders languages from database and sets default session', function (): void {
    // Clear any previously set session value (from Pest.php beforeEach or elsewhere)
    session()->forget('selectedLanguage');

    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // English is auto-created as default, add German
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => false, 'is_active' => true]);

    $component = Volt::test('language-switcher');

    // English is the default (auto-created)
    expect(session('selectedLanguage'))->toBe('en');
    // Should have 2 languages now
    expect($component->get('languages'))->toHaveCount(2);
});

it('changes session language on click', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // English is auto-created as default, add German
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => false, 'is_active' => true]);

    $component = Volt::test('language-switcher');
    $component->call('setLanguage', 'de');
    expect(session('selectedLanguage'))->toBe('de');
});

it('does not render switcher if only one language exists', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Only English exists (auto-created), no additional languages
    // Ensure there's exactly one language for this tenant
    $languageCount = CmsLanguage::where('tenant_id', $tenant->id)->count();
    expect($languageCount)->toBe(1);

    $html = Volt::test('language-switcher')->html();
    expect($html)->toBeString();
    // Switcher should not render language links when only one language exists (count > 1 check)
    expect($html)->not->toContain('<a');
});
