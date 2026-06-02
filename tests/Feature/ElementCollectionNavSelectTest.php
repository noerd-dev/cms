<?php

use Illuminate\Support\ViewErrorBag;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Navigation\CollectionsNavigationProvider;
use Noerd\Cms\Navigation\PageCollectionsNavigationProvider;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
});

it('excludes element collections from the navigation providers', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);
    app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['name' => 'text', 'type' => 'translatableTextarea']],
        'Triggers Demo',
    );

    $links = collect(app(CollectionsNavigationProvider::class)->items())
        ->merge(app(PageCollectionsNavigationProvider::class)->items())
        ->pluck('link')
        ->implode(' ');

    expect(mb_strtoupper($links))->not->toContain('ELEMENT');
});

it('excludes element collections from the collection-select field options', function (): void {
    $this->actingAs($this->user);

    Collection::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_key' => 'NORMALCOLL',
        'name' => 'Sichtbare Collection',
        'is_element_collection' => false,
    ]);

    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);
    app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['name' => 'text', 'type' => 'translatableTextarea']],
        'Versteckte Triggers',
    );

    $html = view('cms::components.forms.input-collection-select', [
        'field' => ['name' => 'rel', 'label' => 'Relation', 'type' => 'collection-select'],
        'errors' => new ViewErrorBag,
    ])->render();

    expect($html)->toContain('Sichtbare Collection')
        ->and($html)->not->toContain('Versteckte Triggers');
});
