<?php

declare(strict_types=1);

use Noerd\Cms\Models\Page;
use Noerd\Cms\Models\Redirect;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Helpers\StaticConfigHelper;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    $this->targetPage = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Über uns'],
        'slug' => ['de' => '/ueber-uns'],
    ]);
});

it('stores a redirect and normalizes the source path', function (): void {
    Livewire::test('cms::redirect-detail')
        ->set('detailData.source_path', '  Agentur/  ')
        ->set('detailData.target_page_id', $this->targetPage->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_redirects', [
        'tenant_id' => $this->tenant->id,
        'source_path' => '/agentur',
        'target_page_id' => $this->targetPage->id,
        'is_active' => 1,
    ]);
});

it('strips a query string and fragment from the source path', function (): void {
    Livewire::test('cms::redirect-detail')
        ->set('detailData.source_path', '/Agentur?utm_source=google#team')
        ->set('detailData.target_page_id', $this->targetPage->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_redirects', ['source_path' => '/agentur']);
});

it('rejects a duplicate source path for the same tenant', function (): void {
    Redirect::factory()->create([
        'tenant_id' => $this->tenant->id,
        'source_path' => '/agentur',
        'target_page_id' => $this->targetPage->id,
    ]);

    Livewire::test('cms::redirect-detail')
        ->set('detailData.source_path', '/Agentur/')
        ->set('detailData.target_page_id', $this->targetPage->id)
        ->call('store')
        ->assertHasErrors('detailData.source_path');

    expect(Redirect::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

it('allows editing an existing redirect without tripping the duplicate check', function (): void {
    $redirect = Redirect::factory()->create([
        'tenant_id' => $this->tenant->id,
        'source_path' => '/agentur',
        'target_page_id' => $this->targetPage->id,
    ]);

    Livewire::test('cms::redirect-detail', ['modelId' => $redirect->id])
        ->set('detailData.is_active', false)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_redirects', [
        'id' => $redirect->id,
        'source_path' => '/agentur',
        'is_active' => 0,
    ]);
});

it('rejects the start page as a source path', function (): void {
    Livewire::test('cms::redirect-detail')
        ->set('detailData.source_path', '/')
        ->set('detailData.target_page_id', $this->targetPage->id)
        ->call('store')
        ->assertHasErrors('detailData.source_path');

    expect(Redirect::count())->toBe(0);
});

it('rejects a redirect pointing at the target page own slug', function (): void {
    Livewire::test('cms::redirect-detail')
        ->set('detailData.source_path', '/ueber-uns')
        ->set('detailData.target_page_id', $this->targetPage->id)
        ->call('store')
        ->assertHasErrors('detailData.source_path');

    expect(Redirect::count())->toBe(0);
});

it('requires a source path and a target page', function (): void {
    Livewire::test('cms::redirect-detail')
        ->call('store')
        ->assertHasErrors(['detailData.source_path', 'detailData.target_page_id']);
});

it('shows the target page name in the list', function (): void {
    Redirect::factory()->create([
        'tenant_id' => $this->tenant->id,
        'source_path' => '/agentur',
        'target_page_id' => $this->targetPage->id,
    ]);

    Livewire::test('cms::redirects-list')
        ->assertSee('/agentur')
        ->assertSee('Über uns');
});

it('renders the detail form with the selected target page', function (): void {
    $redirect = Redirect::factory()->create([
        'tenant_id' => $this->tenant->id,
        'source_path' => '/agentur',
        'target_page_id' => $this->targetPage->id,
    ]);

    Livewire::test('cms::redirect-detail', ['modelId' => $redirect->id])
        ->assertSet('detailData.source_path', '/agentur')
        ->assertSet('relationTitles.target_page_id', 'Über uns')
        // labels come from redirect-detail.yml, so this covers the YAML wiring
        ->assertSee(__('Source Path'))
        ->assertSee(__('Target Page'))
        // the pageRelation field shows the resolved page name
        ->assertSee('Über uns');
});

it('exposes the redirects entry in the cms sidebar navigation', function (): void {
    $navigations = collect(StaticConfigHelper::getNavigationStructure() ?? [])
        ->flatMap(fn (array $app): array => $app['block_menus'] ?? [])
        ->flatMap(fn (array $menu): array => $menu['navigations'] ?? []);

    $entry = $navigations->firstWhere('route', 'cms.redirects');

    expect($entry)->not->toBeNull()
        ->and($entry['title'])->toBe('Redirects')
        ->and($entry['newComponent'])->toBe('cms::redirect-detail');
});
