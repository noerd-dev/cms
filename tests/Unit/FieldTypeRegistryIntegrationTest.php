<?php

declare(strict_types=1);

use Noerd\Services\FieldTypeRegistry;

uses(Tests\TestCase::class);

it('registers cms field types in the shared field type registry', function (): void {
    $registry = app(FieldTypeRegistry::class);

    expect($registry->has('collection-select'))->toBeTrue();
    expect($registry->resolve('collection-select')?->kind)->toBe('include');
    expect($registry->resolve('collection-select')?->target)->toBe('cms::components.forms.input-collection-select');

    expect($registry->has('pageRelation'))->toBeTrue();
    expect($registry->resolve('pageRelation')?->kind)->toBe('livewire');
    expect($registry->resolve('pageRelation')?->target)->toBe('cms-page-relation');
});

it('resolves pageRelation props from nested detail data', function (): void {
    $registry = app(FieldTypeRegistry::class);
    $definition = $registry->resolve('pageRelation');

    $component = new class
    {
        public array $detailData = [
            'custom_attributes' => [
                'page_id' => '17',
            ],
        ];
    };

    $props = $definition?->resolveProps([
        'name' => 'detailData.custom_attributes.page_id',
        'label' => 'booking_label_page',
        'required' => true,
    ], $component, null, 99);

    expect($props)->toBe([
        'fieldName' => 'detailData.custom_attributes.page_id',
        'label' => 'booking_label_page',
        'value' => 17,
        'required' => true,
    ]);

    expect($definition?->resolveKey([
        'name' => 'detailData.custom_attributes.page_id',
    ], $component, null, 99))->toBe('detailData.custom_attributes.page_id-99');
});
