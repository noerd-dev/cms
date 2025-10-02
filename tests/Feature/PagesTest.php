<?php

use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Livewire\Volt\Volt;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('test pages route', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $this->get(route('cms.pages'))
        ->assertStatus(200);
});

it('create a page', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create empty page is possible
    Volt::test('page-detail')
        ->call('store')
        ->assertOk();

    Volt::test('page-detail')
        ->set('model.name.de', 'Test Page')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $tenant->id,
        'name' => '{"de":"Test Page","en":""}',
    ]);

    // Open the page-component for a created page and edit it
    $page = Page::where('tenant_id', $tenant->id)
        ->where('name', '{"de":"Test Page","en":""}')
        ->first();

    Volt::test('page-detail', ['modelId' => $page->id])
        ->set('model.name.en', 'Test Page English')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $tenant->id,
        'name' => '{"de":"Test Page","en":"Test Page English"}',
    ]);
});
