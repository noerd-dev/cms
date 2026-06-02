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

it('persists changes to the row', function (): void {
    Livewire::test('element-collection-row-detail', [
        'modelId' => $this->row->id,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->set('detailData.text.de', 'Geänderter Text')
        ->call('store')
        ->assertDispatched('closeTopModal');

    expect($this->row->fresh()->data['text']['de'])->toBe('Geänderter Text');
});

it('creates a new row when opened without a modelId', function (): void {
    $before = $this->elementCollection->rows()->count();

    Livewire::test('element-collection-row-detail', [
        'modelId' => null,
        'collectionKey' => $this->elementCollection->collection_key,
    ])
        ->set('detailData.text.de', 'Neuer Eintrag')
        ->call('store')
        ->assertDispatched('closeTopModal');

    expect($this->elementCollection->rows()->count())->toBe($before + 1);
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
