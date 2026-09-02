<?php

declare(strict_types=1);

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Helpers\NoerdAuth;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

/**
 * Probe object for the trait — anonymous, so no class name leaks into the
 * global namespace shared by every module suite.
 */
function zzLanguageFilterProbe(): object
{
    return new class {
        use LanguageFilterTrait;

        public function callEnsureDefaultLanguage(): string
        {
            return $this->ensureDefaultLanguage();
        }
    };
}

it('returns default language when session is not set', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    session()->forget('selectedLanguage');

    $testClass = zzLanguageFilterProbe();
    $result = $testClass->callEnsureDefaultLanguage();

    expect($result)->toBe('de');
    expect(session('selectedLanguage'))->toBe('de');
});

it('returns existing session language when it exists in cms_languages', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
    ]);

    CmsLanguage::firstOrCreate(
        ['tenant_id' => $tenant->id, 'code' => 'en'],
        ['name' => 'English', 'is_active' => true, 'is_default' => false],
    );

    session(['selectedLanguage' => 'en']);

    $testClass = zzLanguageFilterProbe();
    $result = $testClass->callEnsureDefaultLanguage();

    expect($result)->toBe('en');
    expect(session('selectedLanguage'))->toBe('en');
});

it('resets to default language when session language does not exist', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

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

    $testClass = zzLanguageFilterProbe();
    $result = $testClass->callEnsureDefaultLanguage();

    expect($result)->toBe('de');
    expect(session('selectedLanguage'))->toBe('de');
});

it('displays pages list without htmlspecialchars error when no session is set', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

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

it('resets to default language when session language is inactive', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

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

    $testClass = zzLanguageFilterProbe();
    $result = $testClass->callEnsureDefaultLanguage();

    expect($result)->toBe('de');
    expect(session('selectedLanguage'))->toBe('de');
});
