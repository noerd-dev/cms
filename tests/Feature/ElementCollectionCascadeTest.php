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
});

it('deletes page-owned element collections and their rows when the owner entry is deleted', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Owner']]);

    $elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'a', 'en' => 'a']], ['text' => ['de' => 'b', 'en' => 'b']]],
        [['name' => 'text', 'type' => 'translatableTextarea']],
        'Triggers Owner',
    );
    $rowIds = $elementCollection->rows()->pluck('id');

    expect($rowIds)->toHaveCount(2);

    $owner->delete();

    expect(Collection::find($elementCollection->id))->toBeNull()
        ->and(Page::whereIn('id', $rowIds)->count())->toBe(0);
});

it('leaves element collections of other entries intact', function (): void {
    $service = app(ElementCollectionService::class);
    $ownerA = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'A']]);
    $ownerB = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'B']]);

    $a = $service->ensure(ElementCollectionService::OWNER_PAGE, $ownerA->id, $ownerA->tenant_id, 'triggers_items', [['name' => 'text', 'type' => 'translatableTextarea']], 'T A');
    $b = $service->ensure(ElementCollectionService::OWNER_PAGE, $ownerB->id, $ownerB->tenant_id, 'triggers_items', [['name' => 'text', 'type' => 'translatableTextarea']], 'T B');

    $ownerA->delete();

    expect(Collection::find($a->id))->toBeNull()
        ->and(Collection::find($b->id))->not->toBeNull();
});

it('deletes an element_page-owned element collection when the element instance is deleted', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Page']]);
    $element = ElementPage::create(['page_id' => $page->id, 'element_key' => 'team_section', 'data' => '{}', 'sort' => 0]);

    $elementCollection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_ELEMENT_PAGE,
        $element->id,
        $this->tenant->id,
        'members',
        [['name' => 'name', 'type' => 'translatableText']],
        'Team-Mitglieder',
    );

    $element->delete();

    expect(Collection::find($elementCollection->id))->toBeNull();
});

it('deletes element_page-owned collections when the host page is deleted', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Page']]);
    $element = ElementPage::create(['page_id' => $page->id, 'element_key' => 'team_section', 'data' => '{}', 'sort' => 0]);

    $elementCollection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_ELEMENT_PAGE,
        $element->id,
        $this->tenant->id,
        'members',
        [['name' => 'name', 'type' => 'translatableText']],
        'Team-Mitglieder',
    );

    $page->delete();

    expect(Collection::find($elementCollection->id))->toBeNull();
});
