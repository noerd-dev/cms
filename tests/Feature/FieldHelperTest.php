<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Services\PageElementService;
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

it('discovers the elements of a module installed outside app-modules through its Livewire namespace', function (): void {
    // A module required from vendor/ is no app-modules/ folder; it is found
    // through the components folder it registers as its Livewire namespace.
    $components = storage_path('framework/testing/zz-vendor-module-' . getmypid() . '/resources/views/components');
    File::ensureDirectoryExists($components . '/elements');
    File::put($components . '/elements/zz-vendor-text.blade.php', "<div></div>\n");
    File::put($components . '/elements/zz-vendor-text.yml', "title: 'Zz Vendor Text'\ngroup: 'Zz Vendor'\nfields:\n  - name: detailData.text\n    label: Text\n    type: translatableText\n");
    Livewire::addNamespace('zzvendormodule', viewPath: $components);
    FieldHelper::clearCache();

    try {
        expect(FieldHelper::getElementFields('zz_vendor_text')['title'] ?? null)->toBe('Zz Vendor Text')
            ->and(collect(FieldHelper::getAllElements())->pluck('element_key'))->toContain('zz_vendor_text')
            ->and((new PageElementService())->getComponentMapping())->toHaveKey('zz_vendor_text', 'zzvendormodule::elements.zz-vendor-text');
    } finally {
        File::deleteDirectory(dirname($components, 3));
        FieldHelper::clearCache();
    }
});
