<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Services\PageElementService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
});

it('imports inline repeater items into element collection rows in order', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Strategie & Konzept']]);

    $elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'T1', 'en' => 'E1']], ['text' => ['de' => 'T2', 'en' => 'E2']]],
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'Triggers Strategie & Konzept',
    );

    $rows = $elementCollection->rows()->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->data)->toBe(['text' => ['de' => 'T1', 'en' => 'E1']])
        ->and($rows[1]->data)->toBe(['text' => ['de' => 'T2', 'en' => 'E2']]);
});

it('does not duplicate rows when importItems runs twice', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);
    $service = app(ElementCollectionService::class);
    $items = [['text' => ['de' => 'a', 'en' => 'a']]];
    $fields = [['name' => 'text', 'type' => 'translatableTextarea']];

    $service->importItems(ElementCollectionService::OWNER_PAGE, $owner->id, $owner->tenant_id, 'triggers_items', $items, $fields, 'T');
    $service->importItems(ElementCollectionService::OWNER_PAGE, $owner->id, $owner->tenant_id, 'triggers_items', $items, $fields, 'T');

    expect(Collection::where('page_id', $owner->id)->where('owner_field', 'triggers_items')->first()->rows()->count())
        ->toBe(1);
});

it('reinjects element collection rows into processed collection page data', function (): void {
    $owner = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Demo', 'en' => 'Demo'],
        'data' => ['triggers_title' => ['de' => 'Titel DE', 'en' => 'Title EN']],
    ]);

    app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'Trigger eins', 'en' => 'Trigger one']]],
        [['name' => 'text', 'type' => 'translatableTextarea']],
        'Triggers Demo',
    );

    $service = app(PageElementService::class);
    $de = $service->processCollectionPageData($owner->fresh(), 'de');
    $en = $service->processCollectionPageData($owner->fresh(), 'en');

    expect($de['triggers_items'])->toHaveCount(1)
        ->and($de['triggers_items'][0]['text'])->toBe('Trigger eins')
        ->and($de['triggers_title'])->toBe('Titel DE')
        ->and($en['triggers_items'][0]['text'])->toBe('Trigger one');
});
