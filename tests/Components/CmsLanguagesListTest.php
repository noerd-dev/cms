<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'listName' => 'cms-languages-list',
    'componentName' => 'cms-language-detail',
];

it('resolves cms languages route and renders table', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    $response = $this->get(route('cms.languages'));
    $response->assertStatus(200);

    Volt::test($testSettings['listName'])
        ->assertViewIs('volt-livewire::cms-languages-list');
});

it('lists languages for tenant in table with sorting and search', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    // English is auto-created as default, add German
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => false]);

    Volt::test($testSettings['listName'])
        ->set('search', 'Eng')
        ->call('with')
        ->assertSet('search', 'Eng');
});

it('opens cms-language-detail modal from table', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    Volt::test($testSettings['listName'])
        ->call('tableAction', 5)
        ->assertDispatched('noerdModal', component: 'cms-language-detail');
});
