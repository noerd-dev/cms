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

it('imports repeater items in order and is idempotent on a second run', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Strategie & Konzept']]);
    $service = app(ElementCollectionService::class);
    $items = [['text' => ['de' => 'T1', 'en' => 'E1']], ['text' => ['de' => 'T2', 'en' => 'E2']]];
    $fields = [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]];

    $service->importItems(ElementCollectionService::OWNER_PAGE, $owner->id, $owner->tenant_id, 'triggers_items', $items, $fields, 'Triggers Strategie & Konzept');
    $elementCollection = $service->importItems(ElementCollectionService::OWNER_PAGE, $owner->id, $owner->tenant_id, 'triggers_items', $items, $fields, 'Triggers Strategie & Konzept');

    $rows = $elementCollection->rows()->get();

    expect(Collection::where('page_id', $owner->id)->where('owner_field', 'triggers_items')->count())->toBe(1)
        ->and($rows)->toHaveCount(2)
        ->and($rows[0]->data)->toBe(['text' => ['de' => 'T1', 'en' => 'E1']])
        ->and($rows[1]->data)->toBe(['text' => ['de' => 'T2', 'en' => 'E2']]);
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
