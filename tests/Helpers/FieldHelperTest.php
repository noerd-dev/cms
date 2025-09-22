<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Helpers\FieldHelper;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('returns null when element yml is missing', function (): void {
    $fields = FieldHelper::getElementFields('____missing____');
    expect($fields)->toBeNull();
});

it('parses element data with defaults and translations', function (): void {
    // Use a real element from content; fallback when not present by skipping
    $element = 'text_block_1_column';

    $elementFileName = str_replace('_', '-', $element);
    if (! file_exists(base_path('app-modules/website/resources/views/livewire/elements/' . $elementFileName . '.yml'))
        && ! file_exists(base_path('content/elements/' . $element . '.yml'))
    ) {
        $this->markTestSkipped('No element yml found for parsing test');
    }

    $fields = FieldHelper::getElementFields($element);
    expect($fields)->toBeArray()->and($fields)->toHaveKey('fields');

    $parsed = FieldHelper::parseElementToData($element, ['name' => 'MyName']);
    expect($parsed)->toBeArray();
    // Should set translatable defaults for de/en when applicable
    foreach ($fields['fields'] as $field) {
        if (($field['type'] ?? null) && in_array($field['type'], ['translatableText', 'translatableRichText'])) {
            $key = str_replace('model.', '', $field['name']);
            expect($parsed[$key]['de'])->toBeString();
            expect($parsed[$key]['en'])->toBeString();
            break;
        }
    }
});
