<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Media\Models\Media;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);
    $this->elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'Erster Trigger', 'en' => 'First trigger']]],
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'Triggers Demo',
    );
    $this->row = $this->elementCollection->rows()->first();
});

/**
 * A real page element to own an element collection (the FK rejects orphans).
 */
function zzRowDetailElementId(int $tenantId): int
{
    $page = Page::factory()->create(['tenant_id' => $tenantId, 'name' => ['de' => 'Owner']]);

    return ElementPage::create(['page_id' => $page->id, 'element_key' => 'testimonials', 'data' => [], 'sort' => 0])->id;
}

it('loads the row data on first mount', function (): void {
    Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->assertSet('detailData.text.de', 'Erster Trigger')
        ->assertSet('detailData.text.en', 'First trigger')
        ->assertSee('Triggers Demo');
});

it('initializes the row from the entryId query parameter', function (): void {
    Livewire::withQueryParams(['entryId' => $this->row->id])
        ->test('element-collection-row-detail', [
            'collectionKey' => $this->elementCollection->collection_key,
        ])
        ->assertSet('modelId', $this->row->id)
        ->assertSet('detailData.text.de', 'Erster Trigger');
});

it('persists changes to the row and keeps the modal open', function (): void {
    Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->set('detailData.text.de', 'Geänderter Text')
        ->call('store')
        ->assertDispatched('refreshList-collection-entries-list')
        ->assertDispatched('refreshList-element-collection-field')
        ->assertNotDispatched('closeTopModal');

    expect($this->row->fresh()->data['text']['de'])->toBe('Geänderter Text');
});

it('creates a new row when opened without a modelId and keeps the modal open', function (): void {
    $before = $this->elementCollection->rows()->count();

    Livewire::test('element-collection-row-detail', [
        'modelId' => null,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->set('detailData.text.de', 'Neuer Eintrag')
        ->call('store')
        ->assertDispatched('refreshList-collection-entries-list')
        ->assertDispatched('refreshList-element-collection-field')
        ->assertNotDispatched('closeTopModal');

    expect($this->elementCollection->rows()->count())->toBe($before + 1);
});

it('persists a changed sort value', function (): void {
    Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->set('detailData.sort', 5)
        ->call('store');

    expect($this->row->fresh()->sort)->toBe(5);
});

it('renders the copy button only for existing rows', function (): void {
    Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])->assertSee(__('Copy this entry?'));

    Livewire::test('element-collection-row-detail', [
        'modelId' => null,
        'collectionKey' => $this->elementCollection->collection_key,
    ])->assertDontSee(__('Copy this entry?'));
});

it('copies the row, inserts it after the original and switches to the copy', function (): void {
    $secondRow = Page::create([
        'tenant_id' => $this->elementCollection->tenant_id,
        'collection_id' => $this->elementCollection->id,
        'data' => ['text' => ['de' => 'Zweiter Trigger']],
        'sort' => ($this->row->sort ?? 0) + 1,
        'is_active' => true,
    ]);

    $before = $this->elementCollection->rows()->count();

    $component = Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->call('copy')
        ->assertDispatched('refreshList-collection-entries-list')
        ->assertDispatched('refreshList-element-collection-field')
        ->assertNotDispatched('closeTopModal');

    expect($this->elementCollection->rows()->count())->toBe($before + 1);

    $copyId = $component->get('modelId');
    $copy = Page::find($copyId);

    expect($copyId)->not->toBe($this->row->id)
        ->and($copy->data)->toBe($this->row->fresh()->data)
        ->and($copy->sort)->toBe(($this->row->fresh()->sort ?? 0) + 1)
        ->and($secondRow->fresh()->sort)->toBe($copy->sort + 1);
});

it('deletes the row', function (): void {
    $rowId = $this->row->id;

    Livewire::test('element-collection-row-detail', [
        'modelId' => $rowId,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->call('delete')
        ->assertDispatched('closeTopModal');

    expect(Page::find($rowId))->toBeNull();
});

it('stores a picked media file in an image row field only for the matching token', function (): void {
    $collection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_ELEMENT_PAGE,
        zzRowDetailElementId($this->tenant->id),
        $this->tenant->id,
        'collection_id',
        [['name' => 'Jane Doe', 'logo' => '']],
        [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
            ['name' => 'logo', 'label' => 'Logo', 'type' => 'image', 'colspan' => 12],
        ],
        'Testimonials Demo',
    );
    $row = $collection->rows()->first();

    $media = Media::factory()->create([
        'tenant_id' => $this->tenant->id,
        'path' => 'logo.svg',
        'disk' => 'media',
    ]);

    $component = Livewire::test('element-collection-row-detail', [
        'modelId' => $row->id,
        'collectionKey' => $collection->collection_key,
    ]);

    // Without an open selection there is no token, so the event must be ignored.
    $component->call('mediaSelected', $media->id, 'logo', 'fremder-token')
        ->assertSet('detailData.logo', '');

    $component->call('openSelectMediaModal', 'logo');
    $token = $component->get('mediaToken');

    expect($token)->toBeString()->not->toBeEmpty();

    $component->call('mediaSelected', $media->id, 'logo', $token)
        ->assertSet('detailData.logo', '/storage/media/logo.svg')
        ->assertSet('mediaToken', null)
        ->call('store');

    expect($row->fresh()->data['logo'])->toBe('/storage/media/logo.svg');
});

it('clears an image row field', function (): void {
    $collection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_ELEMENT_PAGE,
        zzRowDetailElementId($this->tenant->id),
        $this->tenant->id,
        'collection_id',
        [['name' => 'Jane Doe', 'logo' => '/storage/media/logo.svg']],
        [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
            ['name' => 'logo', 'label' => 'Logo', 'type' => 'image', 'colspan' => 12],
        ],
        'Testimonials Demo',
    );
    $row = $collection->rows()->first();

    Livewire::test('element-collection-row-detail', [
        'modelId' => $row->id,
        'collectionKey' => $collection->collection_key,
    ])
        ->assertSet('detailData.logo', '/storage/media/logo.svg')
        ->call('deleteImage', 'logo')
        ->assertSet('detailData.logo', null)
        ->call('store');

    expect($row->fresh()->data['logo'])->toBeNull();
});
