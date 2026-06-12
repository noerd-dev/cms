<?php


use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'cms::language-detail',
    'listName' => 'cms::languages-list',
    'id' => 'modelId',
    'urlParam' => 'cmsLanguageId',
];

it('validates the language data', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($admin);

    $component = Livewire::test($testSettings['componentName'])
        ->set('detailData', [])
        ->call('store');

    $component->assertHasErrors(requiredLayoutFields($component));
});

it('creates a new language and stores tenant_id', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('detailData', validDetailPayload(CmsLanguage::class, [
            'tenant_id' => $tenant->id,
            'code' => 'de',
            'name' => 'Deutsch',
            'is_default' => true,
        ]))
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('cms_languages', [
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_default' => true,
    ]);
});

it('ensures only one default language per tenant', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($admin);

    // Delete any auto-created languages and set up test-specific languages
    CmsLanguage::where('tenant_id', $tenant->id)->delete();

    // Create English as initial default
    CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'en',
        'name' => 'English',
        'is_default' => true,
        'is_active' => true,
    ]);

    // Create German as new default via component
    Livewire::test($testSettings['componentName'])
        ->set('detailData', validDetailPayload(CmsLanguage::class, [
            'tenant_id' => $tenant->id,
            'code' => 'de',
            'name' => 'Deutsch',
            'is_default' => true,
        ]))
        ->call('store');

    // German should now be default, English should not
    $this->assertDatabaseHas('cms_languages', [
        'code' => 'de',
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $this->assertDatabaseHas('cms_languages', [
        'code' => 'en',
        'tenant_id' => $tenant->id,
        'is_default' => false,
    ]);
});

it('updates an existing language', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    $language = CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'fr',
        'name' => 'Français',
        'is_default' => false,
    ]);

    Livewire::withUrlParams([$testSettings['urlParam'] => $language->id])
        ->test($testSettings['componentName'])
        ->set('detailData.name', 'Französisch')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('cms_languages', [
        'id' => $language->id,
        'name' => 'Französisch',
    ]);
});

it('deletes a language', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    $language = CmsLanguage::create([
        'tenant_id' => $tenant->id,
        'code' => 'it',
        'name' => 'Italiano',
        'is_default' => false,
    ]);

    Livewire::withUrlParams([$testSettings['urlParam'] => $language->id])
        ->test($testSettings['componentName'])
        ->call('delete')
        ->assertDispatched('closeTopModal');

    $this->assertDatabaseMissing('cms_languages', ['id' => $language->id]);
});
