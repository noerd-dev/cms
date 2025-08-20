<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\Language;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

$testSettings = [
    'componentName' => 'setup.language-component',
    'listName' => 'languages-table',
    'id' => 'languageId',
];

it('validates the language data', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->call('store')
        ->assertHasErrors(['model.code', 'model.name']);
});

it('creates a new language and stores tenant_id', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('model.code', 'de')
        ->set('model.name', 'Deutsch')
        ->set('model.is_active', true)
        ->set('model.is_default', true)
        ->set('model.sort_order', 1)
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('cms_languages', [
        'tenant_id' => $admin->selected_tenant_id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);
});

it('ensures only one default language per tenant', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    // First default language
    Volt::test($testSettings['componentName'])
        ->set('model.code', 'de')
        ->set('model.name', 'Deutsch')
        ->set('model.is_active', true)
        ->set('model.is_default', true)
        ->call('store');

    // Second default language should unset default on first
    Volt::test($testSettings['componentName'])
        ->set('model.code', 'en')
        ->set('model.name', 'English')
        ->set('model.is_active', true)
        ->set('model.is_default', true)
        ->call('store');

    $this->assertDatabaseHas('cms_languages', [
        'code' => 'en',
        'tenant_id' => $admin->selected_tenant_id,
        'is_default' => true,
    ]);

    $this->assertDatabaseHas('cms_languages', [
        'code' => 'de',
        'tenant_id' => $admin->selected_tenant_id,
        'is_default' => false,
    ]);
});

it('updates an existing language', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $this->actingAs($admin);

    $language = Language::create([
        'tenant_id' => $admin->selected_tenant_id,
        'code' => 'fr',
        'name' => 'Français',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 3,
    ]);

    Volt::test($testSettings['componentName'], ['modelId' => $language->id])
        ->set('model.name', 'Französisch')
        ->set('model.is_active', false)
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('cms_languages', [
        'id' => $language->id,
        'name' => 'Französisch',
        'is_active' => false,
    ]);
});

it('deletes a language', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $this->actingAs($admin);

    $language = Language::create([
        'tenant_id' => $admin->selected_tenant_id,
        'code' => 'it',
        'name' => 'Italiano',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 4,
    ]);

    Volt::test($testSettings['componentName'], ['modelId' => $language->id])
        ->call('delete')
        ->assertDispatched('reloadTable-' . $testSettings['listName']);

    $this->assertDatabaseMissing('cms_languages', ['id' => $language->id]);
});


