<?php

declare(strict_types=1);

use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\RelationFieldRegistry;
use Tests\TestCase;

uses(TestCase::class);

it('registers cms field types in the shared field type registry', function (): void {
    $registry = app(FieldTypeRegistry::class);

    expect($registry->has('collection-select'))->toBeTrue();
    expect($registry->resolve('collection-select')?->kind)->toBe('include');
    expect($registry->resolve('collection-select')?->target)->toBe('cms::components.forms.input-collection-select');

    expect($registry->has('pageRelation'))->toBeTrue();
    expect($registry->resolve('pageRelation')?->kind)->toBe('livewire');
    expect($registry->resolve('pageRelation')?->target)->toBe('noerd-relation-field');
});

it('resolves pageRelation props from nested detail data', function (): void {
    $registry = app(FieldTypeRegistry::class);
    $definition = $registry->resolve('pageRelation');

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

    expect($props)->toBe([
        'relationType' => 'pageRelation',
        'fieldName' => 'detailData.custom_attributes.page_id',
        'label' => 'Page',
        'value' => '17',
        'required' => true,
        'readonly' => false,
        'helpText' => '',
        'modelId' => 99,
        'owner' => null,
        'errorMessages' => [],
        'theme' => 'default',
    ]);

    expect($definition?->resolveKey([
        'name' => 'detailData.custom_attributes.page_id',
    ], $component, null, 99))->toBe('pageRelation-detailData.custom_attributes.page_id-99');
});

it('registers pageRelation metadata in the relation field registry', function (): void {
    $registry = app(RelationFieldRegistry::class);
    $definition = $registry->resolve('pageRelation');

    expect($definition)->not->toBeNull();
    expect($definition?->listComponent)->toBe('cms::pages-list');
    expect($definition?->getDetailComponent())->toBe('cms::page-detail');
    expect($definition?->getSelectEvent())->toBe('pageSelected');
});
