<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Services\CollectionEntryStore;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesCollectionDefinitions;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesCollectionDefinitions::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    DatabaseCollectionDefinitionRepository::resetCache();
    $this->store = app(CollectionEntryStore::class);
    $this->layout = [
        'hasPage' => true,
        'fields' => [
            ['name' => 'detailData.headline', 'label' => 'Headline', 'type' => 'text'],
            ['name' => 'detailData.client', 'label' => 'Client', 'type' => 'text'],
            ['name' => '', 'type' => 'spacer'],
        ],
    ];
});

it('strips the detailData prefix and keeps only layout fields', function (): void {
    expect($this->store->fieldNames($this->layout))->toBe(['headline', 'client'])
        ->and($this->store->extract($this->layout, ['headline' => 'A', 'client' => 'B', 'name' => ['en' => 'x'], 'other' => 1]))
        ->toBe(['headline' => 'A', 'client' => 'B']);
});

it('resolves the parent collection by the definition key, not the URL key', function (): void {
    $this->zzCollectionDefinition($this->tenantId, 'team-members', [], hasPage: false);

    $collection = $this->store->parentCollection('team-members', $this->tenantId, $this->user->id);

    expect($collection->collection_key)->toBe('TEAM_MEMBERS')
        ->and(Collection::where('tenant_id', $this->tenantId)->count())->toBe(1);
});

it('reports the missing default-language name and slug', function (): void {
    expect($this->store->requiredFieldErrors(['name' => ['en' => ''], 'slug' => ['en' => '']], 'en'))
        ->toHaveKeys(['detailData.name', 'detailData.slug'])
        ->and($this->store->requiredFieldErrors(['name' => ['en' => 'Team'], 'slug' => ['en' => '/team']], 'en'))
        ->toBe([]);
});

it('persists an entry with page features and derives a unique slug from the name', function (): void {
    $this->zzCollectionDefinition($this->tenantId, 'projects', $this->layout['fields']);
    Page::factory()->create(['tenant_id' => $this->tenantId, 'slug' => ['en' => '/bridge']]);

    $entry = $this->store->persist('projects', $this->layout, [
        'name' => ['en' => 'Bridge'],
        'slug' => ['en' => ''],
        'headline' => 'Big bridge',
        'client' => 'ACME',
        'ignored' => 'x',
    ], null, $this->tenantId, $this->user->id, 'en');

    expect($entry->slug['en'])->toBe('/bridge-2')
        ->and($entry->name['en'])->toBe('Bridge')
        ->and($entry->data)->toBe(['headline' => 'Big bridge', 'client' => 'ACME'])
        ->and($entry->collection->collection_key)->toBe('PROJECTS');
});

it('persists a data-only entry without name and slug', function (): void {
    $this->zzCollectionDefinition($this->tenantId, 'faq', $this->layout['fields'], hasPage: false);
    $layout = ['hasPage' => false] + $this->layout;

    $entry = $this->store->persist('faq', $layout, ['headline' => 'Why?', 'client' => '', 'sort' => 3], null, $this->tenantId, null, 'en');

    expect($entry->name)->toBeNull()
        ->and($entry->slug)->toBeNull()
        ->and($entry->sort)->toBe(3);
});

it('appends new rows and inserts duplicates right after the original', function (): void {
    $elementCollection = Collection::create(['tenant_id' => $this->tenantId, 'collection_key' => 'ELEMENT_1_ITEMS', 'name' => 'Items', 'is_element_collection' => true]);
    $layout = ['fields' => [['name' => 'detailData.text', 'type' => 'text']]];

    $first = $this->store->persistRow($elementCollection, $layout, ['text' => 'one'], null);
    $second = $this->store->persistRow($elementCollection, $layout, ['text' => 'two'], null);
    $copy = $this->store->duplicateRow($first);

    expect([$first->sort, $second->fresh()->sort, $copy->sort])->toBe([0, 2, 1])
        ->and($copy->data)->toBe(['text' => 'one']);
});
