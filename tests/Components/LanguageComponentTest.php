<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Noerd\Models\Language;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'setup.language-detail',
    'listName' => 'languages-list',
    'id' => 'languageId',
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

    $this->assertDatabaseHas('languages', [
        'tenant_id' => $tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_default' => true,
    ]);
});

it('ensures only one default language per tenant', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($admin);

    // First default language
    Volt::test($testSettings['componentName'])
        ->set('model.code', 'de')
        ->set('model.name', 'Deutsch')
        ->set('model.is_default', true)
        ->call('store');

    // Second default language should unset default on first
    Volt::test($testSettings['componentName'])
        ->set('model.code', 'en')
        ->set('model.name', 'English')
        ->set('model.is_default', true)
        ->call('store');

    $this->assertDatabaseHas('languages', [
        'code' => 'en',
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $this->assertDatabaseHas('languages', [
        'code' => 'de',
        'tenant_id' => $tenant->id,
        'is_default' => false,
    ]);
});

it('updates an existing language', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    $language = Language::create([
        'tenant_id' => $tenant->id,
        'code' => 'fr',
        'name' => 'Français',
        'is_default' => false,
    ]);

    Volt::test($testSettings['componentName'], ['modelId' => $language->id])
        ->set('model.name', 'Französisch')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('languages', [
        'id' => $language->id,
        'name' => 'Französisch',
    ]);
});

it('deletes a language', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    $language = Language::create([
        'tenant_id' => $tenant->id,
        'code' => 'it',
        'name' => 'Italiano',
        'is_default' => false,
    ]);

    Volt::test($testSettings['componentName'], ['modelId' => $language->id])
        ->call('delete')
        ->assertDispatched('reloadTable-'.$testSettings['listName']);

    $this->assertDatabaseMissing('languages', ['id' => $language->id]);
});
