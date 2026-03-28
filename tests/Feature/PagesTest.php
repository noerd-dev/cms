<?php


use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

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
    Livewire::test('page-detail')
        ->call('store')
        ->assertOk();

    Livewire::test('page-detail')
        ->set('detailData.name.en', 'Test Page')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $tenant->id,
        'name' => '{"en":"Test Page"}',
    ]);

    // Open the page-component for a created page and edit it
    $page = Page::where('tenant_id', $tenant->id)
        ->where('name', '{"en":"Test Page"}')
        ->first();

    Livewire::test('page-detail', ['pageId' => $page->id])
        ->set('detailData.name.en', 'Test Page')
        ->set('detailData.name.de', 'Test Page German')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $tenant->id,
        'name' => '{"en":"Test Page","de":"Test Page German"}',
    ]);
});
