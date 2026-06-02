<?php

use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);
});

it('lists element collection rows and hides the manage-collection action', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);

    $elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'Mein Trigger DE', 'en' => 'My Trigger EN']]],
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'Triggers Demo',
    );

    Livewire::test('collection-entries-list', [
        'collectionKey' => $elementCollection->collection_key,
        'elementCollection' => true,
    ])
        ->assertSet('elementCollection', true)
        ->assertSee('My Trigger EN')
        ->assertDontSee('Manage Collection');
});

it('opens the dedicated element-collection row editor (not page-detail) from the list', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);

    $elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'Zeile', 'en' => 'Row']]],
        [['name' => 'text', 'type' => 'translatableTextarea']],
        'Triggers Demo',
    );

    Livewire::test('collection-entries-list', [
        'collectionKey' => $elementCollection->collection_key,
        'elementCollection' => true,
    ])
        ->call('listAction', $elementCollection->rows()->first()->id)
        ->assertDispatched('noerdModal', modalComponent: 'cms::element-collection-row-detail');
});
