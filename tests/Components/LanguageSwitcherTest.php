<?php


use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('renders languages from database and sets default session', function (): void {
    // Clear any previously set session value
    session()->forget('selectedLanguage');

    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Delete any auto-created languages and set up test-specific languages
    CmsLanguage::where('tenant_id', $tenant->id)->delete();

    // Create test languages - German as default
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'en', 'name' => 'English', 'is_default' => false, 'is_active' => true]);

    $component = Livewire::test('language-switcher');

    // German is the default
    expect(session('selectedLanguage'))->toBe('de');
    // Should have 2 languages
    expect($component->get('languages'))->toHaveCount(2);
});

it('changes session language on click', function (): void {
    session()->forget('selectedLanguage');

    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Delete any auto-created languages and set up test-specific languages
    CmsLanguage::where('tenant_id', $tenant->id)->delete();

    // Create test languages
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'en', 'name' => 'English', 'is_default' => false, 'is_active' => true]);

    $component = Livewire::test('language-switcher');
    $component->call('setLanguage', 'en');
    expect(session('selectedLanguage'))->toBe('en');
});

it('does not render switcher if only one language exists', function (): void {
    session()->forget('selectedLanguage');

    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Delete any auto-created languages and set up exactly one language
    CmsLanguage::where('tenant_id', $tenant->id)->delete();
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);

    $languageCount = CmsLanguage::where('tenant_id', $tenant->id)->count();
    expect($languageCount)->toBe(1);

    $html = Livewire::test('language-switcher')->html();
    expect($html)->toBeString();
    // Switcher should not render language links when only one language exists (count > 1 check)
    expect($html)->not->toContain('<a');
});
