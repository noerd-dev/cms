<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);
});

it('reopens page detail and the element collection overlay from the URL', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id]);
    $collection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_PAGE,
        $page->id,
        $this->tenant->id,
        'slides',
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableText']],
        'Slides',
    );

    Livewire::withQueryParams(['pageId' => $page->id, 'collection' => $collection->id])
        ->test('cms::pages-list')
        ->assertDispatched('noerdModal', modalComponent: 'cms::page-detail')
        ->assertDispatched(
            'noerdModal',
            modalComponent: 'cms::collection-entries-list',
            arguments: ['collectionKey' => $collection->id, 'elementCollection' => true],
        );
});

it('reopens a regular collection overlay from the URL without the element flag', function (): void {
    CollectionDefinition::create([
        'tenant_id' => $this->tenant->id,
        'filename' => 'standort',
        'key' => 'STANDORT',
        'title' => 'Standort',
        'title_list' => 'Standorte',
        'has_page' => false,
        'fields' => [['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6]],
    ]);
    $collection = Collection::create([
        'tenant_id' => $this->tenant->id,
        'collection_key' => 'STANDORT',
        'name' => 'Standorte',
    ]);

    Livewire::withQueryParams(['collection' => $collection->id])
        ->test('cms::pages-list')
        ->assertDispatched(
            'noerdModal',
            modalComponent: 'cms::collection-entries-list',
            arguments: ['collectionKey' => $collection->id, 'elementCollection' => false],
        );
});

it('reopens page detail, collection overlay and the row editor from the URL', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id]);
    $collection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $page->id,
        $this->tenant->id,
        'slides',
        [['text' => ['de' => 'Erste Zeile']]],
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableText', 'colspan' => 12]],
        'Slides',
    );
    $row = $collection->rows()->first();

    Livewire::withQueryParams(['pageId' => $page->id, 'collection' => $collection->id, 'entry' => $row->id])
        ->test('cms::pages-list')
        ->assertDispatched('noerdModal', modalComponent: 'cms::page-detail')
        ->assertDispatched('noerdModal', modalComponent: 'cms::collection-entries-list')
        ->assertDispatched(
            'noerdModal',
            modalComponent: 'cms::element-collection-row-detail',
            arguments: ['modelId' => $row->id, 'collectionKey' => strtolower($collection->collection_key)],
        );
});

it('ignores an entry that does not belong to the collection in the URL', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id]);
    $collection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_PAGE,
        $page->id,
        $this->tenant->id,
        'slides',
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableText']],
        'Slides',
    );
    $foreignRow = Page::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::withQueryParams(['collection' => $collection->id, 'entry' => $foreignRow->id])
        ->test('cms::pages-list')
        ->assertDispatched('noerdModal', modalComponent: 'cms::collection-entries-list')
        ->assertNotDispatched('noerdModal', modalComponent: 'cms::element-collection-row-detail');
});

it('ignores an unknown collection reference in the URL', function (string|int $reference): void {
    Livewire::withQueryParams(['collection' => $reference])
        ->test('cms::pages-list')
        ->assertStatus(200)
        ->assertNotDispatched('noerdModal');
})->with(['unknown key' => 'does-not-exist', 'unknown id' => 999999]);
