<?php

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);
    $this->user = $user;
    $this->tenant = $tenant;

    // Set German as the default language for the tenant
    CmsLanguage::where('tenant_id', $user->selected_tenant_id)->delete();
    CmsLanguage::create([
        'tenant_id' => $user->selected_tenant_id,
        'code' => 'de',
        'name' => 'Deutsch',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 0,
    ]);
});

it('auto-generates slug when typing title on new page', function (): void {
    $component = Livewire::test('page-detail')
        ->set('detailData.name.de', 'Meine Seite');

    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/meine-seite');
});

it('appends -2 when slug already exists', function (): void {
    Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'name' => ['de' => 'Test'],
        'slug' => ['de' => '/test'],
    ]);

    $component = Livewire::test('page-detail')
        ->set('detailData.name.de', 'Test');

    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/test-2');
});

it('increments to -3 with multiple duplicates', function (): void {
    Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'name' => ['de' => 'Test'],
        'slug' => ['de' => '/test'],
    ]);

    Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'name' => ['de' => 'Test 2'],
        'slug' => ['de' => '/test-2'],
    ]);

    $component = Livewire::test('page-detail')
        ->set('detailData.name.de', 'Test');

    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/test-3');
});

it('allows same slug for different tenants', function (): void {
    // Create page for a different tenant
    $otherTenant = \Noerd\Models\Tenant::factory()->create();
    Page::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => ['de' => 'Test'],
        'slug' => ['de' => '/test'],
    ]);

    // New page for current tenant should get /test (no conflict)
    $component = Livewire::test('page-detail')
        ->set('detailData.name.de', 'Test');

    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/test');
});

it('does not auto-generate slug after first save', function (): void {
    $page = Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'name' => ['de' => 'Original'],
        'slug' => ['de' => '/original'],
        'layout' => 'weblayout',
    ]);

    // Open existing page and change title
    $component = Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail')
        ->set('detailData.name.de', 'Neuer Titel');

    // Slug should remain unchanged
    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/original');
});

it('allows manual slug editing on saved pages', function (): void {
    $page = Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'name' => ['de' => 'Original'],
        'slug' => ['de' => '/original'],
        'layout' => 'weblayout',
    ]);

    Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail')
        ->set('detailData.slug.de', '/manuell-geaendert')
        ->call('store')
        ->assertOk();

    $updatedPage = Page::find($page->id);
    expect($updatedPage->slug['de'])->toBe('/manuell-geaendert');
});

it('does not overwrite non-empty slug on new page when title changes', function (): void {
    // Start a new page, type a name (slug auto-generates), then change the name
    $component = Livewire::test('page-detail')
        ->set('detailData.name.de', 'Erster Titel');

    // Slug should be auto-generated
    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/erster-titel');

    // Change the title — slug should NOT change because it's already filled
    $component->set('detailData.name.de', 'Zweiter Titel');
    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/erster-titel');
});
