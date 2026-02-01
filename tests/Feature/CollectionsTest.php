<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);
uses(RefreshDatabase::class);

it('create a page', function (): void {
    // Clear any existing Mockery instances to avoid conflicts in parallel tests
    if (class_exists('Mockery')) {
        \Mockery::close();
    }

    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create empty page is possible
    Livewire::test('page-detail')
        ->call('store')
        ->assertOk();

    Livewire::test('page-detail')
        ->set('pageData.name.de', 'Test Page')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $tenant->id,
        'name' => '{"de":"Test Page"}',
    ]);

    // Open the page-component for a created page and edit it
    $page = Page::where('tenant_id', $tenant->id)
        ->where('name', '{"de":"Test Page"}')
        ->first();

    Livewire::test('page-detail', ['pageId' => $page->id])
        ->set('pageData.name.de', 'Test Page')
        ->set('pageData.name.en', 'Test Page English')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $tenant->id,
        'name' => '{"de":"Test Page","en":"Test Page English"}',
    ]);
});
