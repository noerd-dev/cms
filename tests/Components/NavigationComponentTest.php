<?php


use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('renders the navigation component', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('cms::navigation-detail')
        ->assertOk();
});

it('allows storing a navigation without page or link for parent items', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['de' => 'Start', 'en' => 'Start'])
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'page_id' => null,
        'link' => null,
    ]);
});

it('persists parent_id when assigning a parent to an existing navigation', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $parent = Navigation::factory()->create([
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'name' => ['de' => 'Hauptmenü', 'en' => 'Main'],
        'parent_id' => null,
    ]);

    $item = Navigation::factory()->create([
        'tenant_id' => $tenant->id,
        'navigation_key' => 'ITEM',
        'name' => ['de' => 'Punkt', 'en' => 'Item'],
        'parent_id' => null,
    ]);

    Livewire::test('cms::navigation-detail', ['modelId' => $item->id])
        ->set('detailData.parent_id', $parent->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'id' => $item->id,
        'parent_id' => $parent->id,
    ]);
});

it('stores a new sub navigation and inherits the parent navigation_key', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $parent = Navigation::factory()->create([
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'name' => ['de' => 'Hauptmenü', 'en' => 'Main'],
        'parent_id' => null,
    ]);

    Livewire::test('cms::navigation-detail')
        ->set('detailData.name', ['de' => 'Unterpunkt', 'en' => 'Subitem'])
        ->set('detailData.parent_id', $parent->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'tenant_id' => $tenant->id,
        'parent_id' => $parent->id,
        'navigation_key' => 'MAIN',
    ]);
});

it('stores a navigation with page and clears link', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::factory()->create(['tenant_id' => $tenant->id, 'name' => ['de' => 'Seite', 'en' => 'Page']]);

    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['de' => 'Start', 'en' => 'Start'])
        ->set('detailData.link', 'https://example.com')
        ->call('pageSelected', $page->id)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'page_id' => $page->id,
        'link' => null,
    ]);
});

it('stores a navigation with link and clears page', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['de' => 'Kontakt', 'en' => 'Contact'])
        ->set('detailData.link', 'https://example.com/contact')
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cms_navigations', [
        'tenant_id' => $tenant->id,
        'navigation_key' => 'MAIN',
        'link' => 'https://example.com/contact',
        'page_id' => null,
    ]);
});

it('normalizes new_tab default and stores 0 when not set', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['de' => 'Blog', 'en' => 'Blog'])
        ->set('detailData.link', 'https://example.com/blog')
        ->call('store')
        ->assertHasNoErrors();

    $navigation = Navigation::where('tenant_id', $tenant->id)
        ->where('navigation_key', 'MAIN')
        ->first();

    expect($navigation)->not->toBeNull();
    expect((int) $navigation->new_tab)->toBe(0);
});

it('respects language switching behavior for page selection display', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => ['de' => 'Über uns', 'en' => 'About us'],
    ]);

    // default language from session is used internally by component; we ensure action does not error
    Livewire::test('cms::navigation-detail')
        ->call('pageSelected', $page->id)
        ->assertHasNoErrors();
});
