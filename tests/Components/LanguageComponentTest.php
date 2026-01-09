<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'cms-language-detail',
    'listName' => 'cms-languages-list',
    'id' => 'cmsLanguageId',
];

it('validates the language data', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->call('store')
        ->assertHasErrors(['model.code', 'model.name']);
});

it('creates a new language and stores tenant_id', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('model.code', 'de')
        ->set('model.name', 'Deutsch')
        ->set('model.is_default', true)
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
    Volt::test($testSettings['componentName'])
        ->set('model.code', 'de')
        ->set('model.name', 'Deutsch')
        ->set('model.is_default', true)
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

    Volt::test($testSettings['componentName'], ['modelId' => $language->id])
        ->set('model.name', 'Französisch')
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

    Volt::test($testSettings['componentName'], ['modelId' => $language->id])
        ->call('delete')
        ->assertDispatched('reloadTable-' . $testSettings['listName']);

    $this->assertDatabaseMissing('cms_languages', ['id' => $language->id]);
});
