<?php

use Noerd\Cms\Helpers\FieldHelper;

uses(Tests\TestCase::class);

it('flattenFields includes repeater fields as-is with nested fields intact', function (): void {
    $fields = [
        ['name' => 'model.headline', 'type' => 'translatableText'],
        [
            'name' => 'model.items',
            'type' => 'repeater',
            'fields' => [
                ['name' => 'image', 'type' => 'image'],
                ['name' => 'name', 'type' => 'translatableText'],
            ],
        ],
    ];

    $flattened = FieldHelper::flattenFields($fields);

    expect($flattened)->toHaveCount(2);
    expect($flattened[0]['name'])->toBe('model.headline');
    expect($flattened[1]['type'])->toBe('repeater');
    expect($flattened[1]['fields'])->toHaveCount(2);
});

it('parseElementToData initializes empty repeater array when no data exists', function (): void {
    $result = FieldHelper::parseElementToData('detail_cards', null);

    expect($result)->toBeArray();
    expect($result['headline'])->toBe(['de' => '', 'en' => '']);
    expect($result['items'])->toBe([]);
});

it('parseElementToData initializes repeater items with translatable structure', function (): void {
    $data = [
        'headline' => ['de' => 'Test', 'en' => 'Test EN'],
        'items' => [
            [
                'image' => '/storage/test.jpg',
                'name' => ['de' => 'Karte 1', 'en' => 'Card 1'],
                'subheader' => ['de' => 'Sub DE', 'en' => 'Sub EN'],
                'text' => ['de' => '<p>Text DE</p>', 'en' => '<p>Text EN</p>'],
            ],
        ],
    ];

    $result = FieldHelper::parseElementToData('detail_cards', $data);

    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['image'])->toBe('/storage/test.jpg');
    expect($result['items'][0]['name'])->toBe(['de' => 'Karte 1', 'en' => 'Card 1']);
    expect($result['items'][0]['subheader'])->toBe(['de' => 'Sub DE', 'en' => 'Sub EN']);
    expect($result['items'][0]['text'])->toBe(['de' => '<p>Text DE</p>', 'en' => '<p>Text EN</p>']);
});

it('parseElementToData fills missing language keys for repeater items', function (): void {
    $data = [
        'items' => [
            [
                'name' => ['de' => 'Nur Deutsch'],
            ],
        ],
    ];

    $result = FieldHelper::parseElementToData('detail_cards', $data);

    expect($result['items'][0]['name']['de'])->toBe('Nur Deutsch');
    expect($result['items'][0]['name']['en'])->toBe('');
    expect($result['items'][0]['image'])->toBe('');
});
