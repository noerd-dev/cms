<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;
use Noerd\Enums\Profile;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

/*
 | The generic WriteGuardHook covers store()/delete() of every detail. These
 | tests pin the write paths OUTSIDE that hook — element mutators, copy(),
 | manage() — for a read-only profile: nothing is written, nothing is opened.
 */
beforeEach(function (): void {
    $this->actingAsCmsUser(Profile::ReadOnly);
    $this->createElementFixtures();

    $this->page = Page::factory()->create([
        'tenant_id' => $this->tenantId,
        'name' => ['en' => 'Guarded'],
        'slug' => ['en' => '/guarded'],
    ]);
    $this->element = ContentElement::create([
        'content_id' => $this->page->content_id,
        'element_key' => $this->zzTextElementKey(),
        'sort' => 1,
        'data' => ['text' => ['en' => 'Original']],
    ]);
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('blocks every document change of the page editor for a read-only user', function (): void {
    $component = Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail');
    $uid = 'r' . $this->element->id;

    $component
        ->set('contentDocumentDraft', [
            'language' => 'en',
            'sections' => [
                ['kind' => 'text', 'uid' => 'nnew', 'html' => '<p>Injected</p>'],
                ['kind' => 'element', 'uid' => $uid, 'key' => $this->zzTextElementKey(), 'data' => ['text' => ['en' => 'Changed']]],
            ],
        ])
        ->call('store')
        ->call('pickContentDocumentMedia', 'cmsdocabc')
        ->call('manageContentDocumentCollection', $uid, 'items')
        ->call('copy');

    expect(ContentElement::count())->toBe(1)
        ->and(Page::count())->toBe(1)
        ->and($this->element->fresh()->data['text']['en'])->toBe('Original')
        ->and(Collection::count())->toBe(0);
});

it('does not create an element collection on manage() for a read-only user', function (): void {
    Livewire::test('cms::element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => $this->page->id,
        'fieldName' => 'items',
        'label' => 'Items',
        'rowFields' => [['name' => 'text', 'label' => 'Text', 'type' => 'text', 'colspan' => 12]],
    ])
        ->call('manage')
        ->assertNotDispatched('noerdModal');

    expect(Collection::where('page_id', $this->page->id)->exists())->toBeFalse();
});

it('does not copy a collection row for a read-only user', function (): void {
    $collection = Collection::create([
        'tenant_id' => $this->tenantId,
        'collection_key' => 'GUARDED_ROWS',
        'name' => 'Guarded rows',
        'is_element_collection' => true,
        'page_id' => $this->page->id,
        'owner_field' => 'items',
        'element_fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'text', 'colspan' => 12]],
    ]);
    $row = Page::factory()->create([
        'tenant_id' => $this->tenantId,
        'collection_id' => $collection->id,
        'data' => ['text' => 'Row'],
        'sort' => 0,
    ]);

    Livewire::test('cms::element-collection-row-detail', ['modelId' => $row->id, 'collectionKey' => 'guarded_rows'])
        ->call('copy');

    expect(Page::where('collection_id', $collection->id)->count())->toBe(1);
});
