<?php

use Livewire\Attributes\Url;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Tests\TestCase;

uses(TestCase::class);
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

it('loads the row data on first mount', function (): void {
    Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->assertSet('detailData.text.de', 'Erster Trigger')
        ->assertSet('detailData.text.en', 'First trigger')
        ->assertSee('Triggers Demo');
});

it('renders a save button wired to store()', function (): void {
    Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])->assertSeeHtml('wire:click="store"');
});

it('does not bind modelId to a URL parameter (avoids nested-modal clobbering)', function (): void {
    $instance = Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])->instance();

    $urlAttributes = (new ReflectionProperty($instance, 'modelId'))->getAttributes(Url::class);

    expect($urlAttributes)->toBeEmpty();
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

it('renders the sort input', function (): void {
    Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])->assertSeeHtml('wire:model="detailData.sort"');
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
    ])->assertSeeHtml('wire:click="copy"');

    Livewire::test('element-collection-row-detail', [
        'modelId' => null,
        'collectionKey' => $this->elementCollection->collection_key,
    ])->assertDontSeeHtml('wire:click="copy"');
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
