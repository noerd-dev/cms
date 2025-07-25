<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

it('test pages route', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $this->get(route('cms.pages'))
        ->assertStatus(200);
});

it('create a page', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();
    $this->actingAs($user);

    // Create empty page is possible
    Volt::test('page-component')
        ->call('store')
        ->assertOk();

    Volt::test('page-component')
        ->set('model.name.de', 'Test Page')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $user->selected_tenant_id,
        'name' => '{"de":"Test Page","en":""}',
    ]);

    // Open the page-component for a created page and edit it
    $page = Page::where('tenant_id', $user->selected_tenant_id)
        ->where('name', '{"de":"Test Page","en":""}')
        ->first();

    Volt::test('page-component', ['modelId' =>  $page->id])
        ->set('model.name.en', 'Test Page English')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $user->selected_tenant_id,
        'name' => '{"de":"Test Page","en":"Test Page English"}',
    ]);
});
