<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ElementPage;
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

it('shows a save-first hint when the owner is not yet saved', function (): void {
    Livewire::test('element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => null,
        'fieldName' => 'triggers_items',
        'label' => 'Triggers — Items',
        'rowFields' => [['name' => 'text', 'type' => 'translatableTextarea']],
    ])->assertSee('zuerst speichern');
});

it('creates a page-owned element collection and opens the modal on manage()', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);

    Livewire::test('element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => $owner->id,
        'fieldName' => 'triggers_items',
        'label' => 'Triggers — Items',
        'rowFields' => [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
    ])
        ->assertSee('Verwalten')
        ->call('manage')
        ->assertDispatched('noerdModal');

    expect(
        Collection::where('page_id', $owner->id)
            ->where('is_element_collection', true)
            ->where('owner_field', 'triggers_items')
            ->exists(),
    )->toBeTrue();
});

it('creates an element_page-owned element collection on manage()', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Page']]);
    $element = ElementPage::create(['page_id' => $page->id, 'element_key' => 'team_section', 'data' => '{}', 'sort' => 0]);

    Livewire::test('element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_ELEMENT_PAGE,
        'ownerId' => $element->id,
        'fieldName' => 'members',
        'label' => 'Team-Mitglieder',
        'rowFields' => [['name' => 'name', 'type' => 'translatableText']],
    ])
        ->assertSee('Verwalten')
        ->call('manage')
        ->assertDispatched('noerdModal');

    expect(
        Collection::where('element_page_id', $element->id)
            ->where('is_element_collection', true)
            ->where('owner_field', 'members')
            ->exists(),
    )->toBeTrue();
});

it('renders the element-collection field through the entry editor instead of [object Object]', function (): void {
    $services = Collection::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'collection_key' => 'SERVICES'],
        ['name' => 'Leistungen', 'is_element_collection' => false],
    );

    $entry = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $services->id,
        'name' => ['de' => 'Demo', 'en' => 'Demo'],
        'data' => [],
    ]);

    // The entry editor renders the SERVICES fields (e.g. the "Titel" field) and the
    // triggers/activities fields no longer stringify their array into "[object Object]".
    Livewire::test('page-detail', ['pageId' => $entry->id, 'collectionKey' => 'services'])
        ->assertSee('Titel')
        ->assertDontSee('[object Object]');
});
