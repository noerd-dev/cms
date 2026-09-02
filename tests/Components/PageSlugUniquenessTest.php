<?php

declare(strict_types=1);

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

it('appends -2 when slug already exists', function (): void {
    Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'name' => ['de' => 'Test'],
        'slug' => ['de' => '/test'],
    ]);

    $component = Livewire::test('cms::page-detail')
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

    $component = Livewire::test('cms::page-detail')
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
    $component = Livewire::test('cms::page-detail')
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

it('updates slug live on new page when title changes', function (): void {
    // Start a new page, type a name (slug auto-generates), then change the name
    $component = Livewire::test('cms::page-detail')
        ->set('detailData.name.de', 'Erster Titel');

    // Slug should be auto-generated
    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/erster-titel');

    // Change the title — slug should update because page is not yet saved
    $component->set('detailData.name.de', 'Zweiter Titel');
    $detailData = $component->get('detailData');
    expect($detailData['slug']['de'])->toBe('/zweiter-titel');
});

describe('language prefix', function (): void {
    /*
     | Slug generation for non-default languages: the default language stays
     | unprefixed, every other active language gets its code as a path prefix,
     | and umlauts transliterate.
     */

    it('prefixes non-default language slugs with the language code', function (): void {
        CmsLanguage::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'en'],
            ['name' => 'English', 'is_active' => true, 'is_default' => false],
        );

        $instance = Livewire::test('cms::page-detail')->instance();

        expect($instance->generateSlug('Über uns', 'de'))->toBe('/ueber-uns')
            ->and($instance->generateSlug('Über uns', 'en'))->toBe('/en/ueber-uns');
    });

    it('keeps the default language unprefixed whatever it is', function (): void {
        $default = CmsLanguage::where('tenant_id', $this->tenant->id)->where('is_default', true)->first();

        expect(Livewire::test('cms::page-detail')->instance()->generateSlug('Startseite', $default->code))
            ->toBe('/startseite');
    });
});
