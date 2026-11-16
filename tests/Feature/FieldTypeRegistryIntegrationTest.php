<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\RelationFieldRegistry;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

it('registers cms field types in the shared field type registry', function (): void {
    $registry = app(FieldTypeRegistry::class);

    expect($registry->has('homepageSelect'))->toBeTrue();
    expect($registry->get('homepageSelect')?->kind)->toBe('include');
    expect($registry->get('homepageSelect')?->target)->toBe('cms::components.forms.input-homepage-select');
    expect($registry->get('elementCollection')?->kind)->toBe('livewire');

    // 0.6 names the types in camelCase; the earlier kebab-case names are gone.
    foreach (['collection-select', 'homepage-select', 'element-collection'] as $removed) {
        expect($registry->has($removed))->toBeFalse();
    }

    expect($registry->has('pageRelation'))->toBeTrue();
    expect($registry->get('pageRelation')?->kind)->toBe('livewire');
    expect($registry->get('pageRelation')?->target)->toBe('noerd::noerd-relation-field');
});

it('resolves pageRelation props from nested detail data', function (): void {
    $registry = app(FieldTypeRegistry::class);
    $definition = $registry->get('pageRelation');

    $component = new class {
        public array $detailData = [
            'custom_attributes' => [
                'page_id' => '17',
            ],
        ];
    };

    $props = $definition?->resolveProps([
        'name' => 'detailData.custom_attributes.page_id',
        'label' => 'Page',
        'required' => true,
    ], $component, null, 99);

    // Only the keys this module contributes are asserted — the rest of the prop
    // set is the noerd relation-field contract and is proven there.
    expect($props)->toMatchArray([
        'relationType' => 'pageRelation',
        'fieldName' => 'detailData.custom_attributes.page_id',
        'value' => '17',
        'modelId' => 99,
    ]);

    expect($definition?->resolveKey([
        'name' => 'detailData.custom_attributes.page_id',
    ], $component, null, 99))->toBe('pageRelation-detailData.custom_attributes.page_id-99');
});

it('registers pageRelation metadata in the relation field registry', function (): void {
    $registry = app(RelationFieldRegistry::class);
    $definition = $registry->get('pageRelation');

    expect($definition)->not->toBeNull();
    expect($definition?->listComponent)->toBe('cms::pages-list');
    expect($definition?->getDetailComponent())->toBe('cms::page-detail');
    expect($definition?->getSelectEvent())->toBe('pageSelected');
});
