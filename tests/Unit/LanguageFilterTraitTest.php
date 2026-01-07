<?php

declare(strict_types=1);

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Traits\LanguageFilterTrait;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

// Create a test class that uses the trait
class TestLanguageFilterClass
{
    use LanguageFilterTrait;

    public function callEnsureDefaultLanguage(): string
    {
        return $this->ensureDefaultLanguage();
    }
}

it('returns default language when session is not set', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    session()->forget('selectedLanguage');

    $testClass = new TestLanguageFilterClass;
    $result = $testClass->callEnsureDefaultLanguage();

    expect($result)->toBe('de');
    expect(session('selectedLanguage'))->toBe('de');
});

it('returns existing session language when it exists in cms_languages', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    CmsLanguage::firstOrCreate(
        ['tenant_id' => $tenant->id, 'code' => 'en'],
        ['name' => 'English', 'is_active' => true, 'is_default' => false]
    );

    session(['selectedLanguage' => 'en']);

    $testClass = new TestLanguageFilterClass;
    $result = $testClass->callEnsureDefaultLanguage();

    expect($result)->toBe('en');
    expect(session('selectedLanguage'))->toBe('en');
});

it('resets to default language when session language does not exist', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Clear any existing languages for this tenant
    CmsLanguage::where('tenant_id', $tenant->id)->delete();

    // Only German exists, no English
    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    // Set session to non-existing language
    session(['selectedLanguage' => 'en']);

    $testClass = new TestLanguageFilterClass;
    $result = $testClass->callEnsureDefaultLanguage();

    expect($result)->toBe('de');
    expect(session('selectedLanguage'))->toBe('de');
});

it('resets to default language when session language is inactive', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Clear any existing languages for this tenant
    CmsLanguage::where('tenant_id', $tenant->id)->delete();

    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    // English exists but is inactive
    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'en',
        'name' => 'English',
        'is_active' => false,
        'is_default' => false,
    ]);

    session(['selectedLanguage' => 'en']);

    $testClass = new TestLanguageFilterClass;
    $result = $testClass->callEnsureDefaultLanguage();

    expect($result)->toBe('de');
    expect(session('selectedLanguage'))->toBe('de');
});
