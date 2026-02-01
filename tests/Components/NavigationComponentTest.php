<?php


use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('renders the navigation component', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('navigation-detail')
        ->assertOk();
});

it('validates that either page or link must be present', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('navigation-detail')
        ->set('navigationData.navigation_key', 'MAIN')
        ->set('navigationData.name', ['de' => 'Start', 'en' => 'Start'])
        ->call('store')
        ->assertHasErrors(['navigationData.page_id' => 'required_without', 'navigationData.link' => 'required_without']);
});

it('stores a navigation with page and clears link', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::factory()->create(['tenant_id' => $tenant->id, 'name' => json_encode(['de' => 'Seite', 'en' => 'Page'])]);

    Livewire::test('navigation-detail')
        ->set('navigationData.navigation_key', 'MAIN')
        ->set('navigationData.name', ['de' => 'Start', 'en' => 'Start'])
        ->set('navigationData.link', 'https://example.com')
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

    Livewire::test('navigation-detail')
        ->set('navigationData.navigation_key', 'MAIN')
        ->set('navigationData.name', ['de' => 'Kontakt', 'en' => 'Contact'])
        ->set('navigationData.link', 'https://example.com/contact')
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

    Livewire::test('navigation-detail')
        ->set('navigationData.navigation_key', 'MAIN')
        ->set('navigationData.name', ['de' => 'Blog', 'en' => 'Blog'])
        ->set('navigationData.link', 'https://example.com/blog')
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
        'name' => json_encode(['de' => 'Über uns', 'en' => 'About us']),
    ]);

    // default language from session is used internally by component; we ensure action does not error
    Livewire::test('navigation-detail')
        ->call('pageSelected', $page->id)
        ->assertHasNoErrors();
});
