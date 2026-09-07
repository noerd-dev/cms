<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'listName' => 'cms::languages-list',
    'componentName' => 'cms::language-detail',
];

it('lists languages for tenant in table with sorting and search', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess(Profile::Admin);
    $this->actingAs($admin, NoerdAuth::guardName());

    // English is auto-created as default, add German
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_active' => true, 'is_default' => false]);

    $component = Livewire::test($testSettings['listName'])
        ->set('search', 'Deutsch');

    $rows = $component->instance()->listData()['rows'];

    expect($rows)->toHaveCount(1);
    expect($rows->first()->name)->toBe('Deutsch');
});

it('opens cms-language-detail modal from table', function () use ($testSettings): void {
    ['user' => $admin, 'tenant' => $tenant] = $this->createUserWithCmsAccess(Profile::Admin);
    $this->actingAs($admin, NoerdAuth::guardName());

    Livewire::test($testSettings['listName'])
        ->call('listAction', 5)
        ->assertDispatched(
            'noerdModal',
            fn(string $event, array $params): bool => ($params['route'] ?? null) === 'cms.language.detail'
                && ($params['arguments']['modelId'] ?? null) === 5,
        );
});
