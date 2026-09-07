<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->createElementFixtures();
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('resolves the field definition of a discovered element', function (): void {
    $fields = FieldHelper::getElementFields($this->zzTextElementKey());

    expect($fields)->not->toBeNull()
        ->and($fields['title'])->toBe('Zz Fixture Text')
        ->and(collect($fields['fields'])->pluck('name')->all())->toBe(['detailData.text']);
});

it('refuses element keys that are not plain identifiers', function (string $key): void {
    // The key is user-supplied through the page editor and ends up in a glob.
    expect(FieldHelper::getElementFields($key))->toBeNull();
})->with([
    'traversal' => '../../../../etc/passwd',
    'wildcard' => '*',
    'nested' => 'elements/../secret',
    'uppercase' => 'Text_Block',
    'empty' => '',
]);

it('groups every discovered element by its YAML group', function (): void {
    $grouped = FieldHelper::getAllElementsGrouped();

    expect($grouped)->toHaveKey('Zz Test')
        ->and(collect($grouped['Zz Test'])->pluck('element_key'))->toContain($this->zzTextElementKey());
});
