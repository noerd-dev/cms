<?php

use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Repositories\ElementAwareCollectionDefinitionRepository;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();

    $this->repo = app(CollectionDefinitionRepositoryContract::class);
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);
    $this->elementCollection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'Triggers Demo',
    );
});

it('binds the element-aware decorator', function (): void {
    expect($this->repo)->toBeInstanceOf(ElementAwareCollectionDefinitionRepository::class);
});

it('resolves the element schema by key', function (): void {
    $fields = $this->repo->resolveFields($this->elementCollection->collection_key);

    expect($fields['hasPage'])->toBeFalse()
        ->and($fields['fields'])->toHaveCount(1)
        ->and($fields['fields'][0]['name'])->toBe('detailData.text');
});

it('synthesizes a definition via find and findByKey for element keys', function (): void {
    expect($this->repo->find($this->elementCollection->collection_key)?->titleList)->toBe('Triggers Demo')
        ->and($this->repo->find($this->elementCollection->collection_key)?->hasPage)->toBeFalse()
        ->and($this->repo->findByKey($this->elementCollection->collection_key)?->titleList)->toBe('Triggers Demo');
});

it('delegates non-element keys to the underlying YAML repository', function (): void {
    $fields = $this->repo->resolveFields('services');

    expect($fields)->not->toBeNull()
        ->and(collect($fields['fields'])->pluck('name'))->toContain('detailData.title');
});

it('excludes element collections from all()', function (): void {
    expect($this->repo->all()->pluck('key'))->not->toContain($this->elementCollection->collection_key);
});

it('delegates and returns null for unknown element-style keys', function (): void {
    expect($this->repo->resolveFields('ELEMENT_999999_NOPE'))->toBeNull()
        ->and($this->repo->find('ELEMENT_999999_NOPE'))->toBeNull();
});
