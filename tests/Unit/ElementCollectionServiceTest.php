<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
});

it('builds a deterministic, uppercased, prefix-stripped key per owner type', function (): void {
    $service = app(ElementCollectionService::class);

    expect($service->keyFor(ElementCollectionService::OWNER_PAGE, 15, 'triggers_items'))->toBe('ELEMENT_15_TRIGGERS_ITEMS')
        ->and($service->keyFor(ElementCollectionService::OWNER_PAGE, 15, 'detailData.triggers_items'))->toBe('ELEMENT_15_TRIGGERS_ITEMS')
        ->and($service->keyFor(ElementCollectionService::OWNER_ELEMENT_PAGE, 15, 'members'))->toBe('ELEMENT_EP_15_MEMBERS')
        // A page owner and an element_page owner with the same id never collide.
        ->and($service->keyFor(ElementCollectionService::OWNER_PAGE, 15, 'members'))
        ->not->toBe($service->keyFor(ElementCollectionService::OWNER_ELEMENT_PAGE, 15, 'members'));
});

it('ensures a page-owned element collection with flags and a detailData-prefixed schema snapshot', function (): void {
    $owner = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Strategie & Konzept', 'en' => 'Strategy & Concept'],
    ]);

    $elementCollection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'Triggers Strategie & Konzept',
    );

    expect($elementCollection->is_element_collection)->toBeTrue()
        ->and($elementCollection->page_id)->toBe($owner->id)
        ->and($elementCollection->element_page_id)->toBeNull()
        ->and($elementCollection->owner_field)->toBe('triggers_items')
        ->and($elementCollection->collection_key)->toBe('ELEMENT_'.$owner->id.'_TRIGGERS_ITEMS')
        ->and($elementCollection->name)->toBe('Triggers Strategie & Konzept')
        ->and($elementCollection->element_fields)->toBe([
            ['name' => 'detailData.text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12],
        ]);
});

it('ensures an element_page-owned element collection on the element_page_id column', function (): void {
    $elementCollection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_ELEMENT_PAGE,
        42,
        $this->tenant->id,
        'members',
        [['name' => 'name', 'type' => 'translatableText']],
        'Team-Mitglieder (Team Section)',
    );

    expect($elementCollection->is_element_collection)->toBeTrue()
        ->and($elementCollection->element_page_id)->toBe(42)
        ->and($elementCollection->page_id)->toBeNull()
        ->and($elementCollection->collection_key)->toBe('ELEMENT_EP_42_MEMBERS');
});

it('is idempotent: ensuring twice returns the same collection', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id]);
    $service = app(ElementCollectionService::class);
    $fields = [['name' => 'text', 'type' => 'translatableTextarea']];

    $first = $service->ensure(ElementCollectionService::OWNER_PAGE, $owner->id, $owner->tenant_id, 'triggers_items', $fields, 'X');
    $second = $service->ensure(ElementCollectionService::OWNER_PAGE, $owner->id, $owner->tenant_id, 'triggers_items', $fields, 'X');

    expect($second->id)->toBe($first->id)
        ->and(Collection::where('collection_key', $service->keyFor(ElementCollectionService::OWNER_PAGE, $owner->id, 'triggers_items'))->count())->toBe(1);
});

it('derives a display name from the field label and owner name', function (): void {
    expect(app(ElementCollectionService::class)->displayName('Triggers — Items', 'Strategie & Konzept'))
        ->toBe('Triggers Strategie & Konzept');
});

it('builds a hasPage:false schema array from the stored row fields', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);
    $service = app(ElementCollectionService::class);
    $elementCollection = $service->ensure(ElementCollectionService::OWNER_PAGE, $owner->id, $owner->tenant_id, 'triggers_items', [['name' => 'text', 'type' => 'translatableTextarea']], 'Triggers Demo');

    $schema = $service->schemaFor($elementCollection);

    expect($schema['hasPage'])->toBeFalse()
        ->and($schema['titleList'])->toBe('Triggers Demo')
        ->and($schema['fields'][0]['name'])->toBe('detailData.text');
});
