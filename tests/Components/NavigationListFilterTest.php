<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    session()->forget(['listFilters', 'selectedLanguage']);

    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    // Create a second language for filter testing
    CmsLanguage::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'code' => 'en'],
        ['name' => 'English', 'is_active' => true, 'is_default' => false],
    );
});

it('can set listFilters without error', function (): void {
    Livewire::test('navigation-list')
        ->set('listFilters.language', 'de')
        ->assertHasNoErrors();
});

it('applies language filter without error', function (): void {
    $component = Livewire::test('navigation-list')
        ->set('listFilters.language', 'en');

    expect($component->get('listFilters')['language'])->toBe('en');
});

it('can filter by navigation_key', function (): void {
    Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'MAIN',
        'name' => json_encode(['de' => 'Startseite']),
        'sort_order' => 0,
    ]);
    Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'FOOTER',
        'name' => json_encode(['de' => 'Impressum']),
        'sort_order' => 0,
    ]);

    $component = Livewire::test('navigation-list')
        ->set('listFilters.navigation_key', 'MAIN')
        ->assertHasNoErrors();

    $listConfig = $component->viewData('listConfig');
    $rows = $listConfig['rows'];

    expect($rows)->toHaveCount(1);
    expect($rows->first()->navigation_key)->toBe('MAIN');
});

it('shows all entries when navigation_key filter is empty', function (): void {
    Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'MAIN',
        'name' => json_encode(['de' => 'Startseite']),
        'sort_order' => 0,
    ]);
    Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'FOOTER',
        'name' => json_encode(['de' => 'Impressum']),
        'sort_order' => 0,
    ]);

    $component = Livewire::test('navigation-list')
        ->set('listFilters.navigation_key', '')
        ->assertHasNoErrors();

    $listConfig = $component->viewData('listConfig');
    $rows = $listConfig['rows'];

    expect($rows)->toHaveCount(2);
});

it('displays children directly after their parent', function (): void {
    $parent1 = Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'MAIN',
        'name' => json_encode(['de' => 'Über uns']),
        'sort_order' => 1,
    ]);
    $parent2 = Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'MAIN',
        'name' => json_encode(['de' => 'Startseite']),
        'sort_order' => 0,
    ]);
    $child1 = Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'MAIN',
        'name' => json_encode(['de' => 'Team']),
        'parent_id' => $parent1->id,
        'sort_order' => 0,
    ]);
    $child2 = Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'MAIN',
        'name' => json_encode(['de' => 'Geschichte']),
        'parent_id' => $parent1->id,
        'sort_order' => 1,
    ]);

    $component = Livewire::test('navigation-list')
        ->assertHasNoErrors();

    $listConfig = $component->viewData('listConfig');
    $rows = $listConfig['rows'];
    $ids = $rows->pluck('id')->toArray();

    // Expected order: Startseite (sort 0), Über uns (sort 1), Team (child sort 0), Geschichte (child sort 1)
    expect($ids)->toBe([$parent2->id, $parent1->id, $child1->id, $child2->id]);
});

it('prefixes child navigation items with arrow indicator', function (): void {
    $parent = Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'MAIN',
        'name' => json_encode(['de' => 'Über uns']),
        'sort_order' => 0,
    ]);
    Navigation::create([
        'tenant_id' => $this->tenant->id,
        'navigation_key' => 'MAIN',
        'name' => json_encode(['de' => 'Team']),
        'parent_id' => $parent->id,
        'sort_order' => 0,
    ]);

    $component = Livewire::test('navigation-list')
        ->assertHasNoErrors();

    $listConfig = $component->viewData('listConfig');
    $rows = $listConfig['rows'];

    expect($rows[0]->name)->toBe('Über uns');
    expect($rows[1]->name)->toBe([
        'prefix' => '↳ ',
        'prefixClass' => 'opacity-50',
        'text' => 'Team',
    ]);
});
