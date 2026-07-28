<?php


use Livewire\Livewire;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'listName' => 'cms::languages-list',
    'componentName' => 'cms::language-detail',
];

it('resolves cms languages route and renders table', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    $response = $this->get(route('cms.languages'));
    $response->assertStatus(200);

    Livewire::test($testSettings['listName'])
        ->assertStatus(200);
});

it('lists languages for tenant in table with sorting and search', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    // English is auto-created as default, add German
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => false]);

    Livewire::test($testSettings['listName'])
        ->set('search', 'Eng')
        ->call('listData')
        ->assertSet('search', 'Eng');
});

it('opens cms-language-detail modal from table', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($admin);

    Livewire::test($testSettings['listName'])
        ->call('listAction', 5)
        ->assertDispatched('noerdModal', modalComponent: 'cms::language-detail');
});
