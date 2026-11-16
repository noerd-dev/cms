<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Helpers\NoerdAuth;
use Noerd\Media\Models\Media;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

/*
 | The row editor of an element collection has no route: the modal stack mounts
 | it with what the browser names. These tests send what an attacker would
 | send — another collection's row, a page that is no row, a foreign schema —
 | and a user without the CMS app.
 */
beforeEach(function (): void {
    $this->actingAsCmsUser();

    $owner = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['de' => 'Owner']]);
    $this->elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $this->tenantId,
        'items',
        [['text' => 'Row one', 'logo' => null]],
        [
            ['name' => 'text', 'label' => 'Text', 'type' => 'text', 'colspan' => 6],
            ['name' => 'logo', 'label' => 'Logo', 'type' => 'image', 'colspan' => 6],
        ],
        'Items Owner',
    );
    $this->row = $this->elementCollection->rows()->first();

    // A plain page of the same tenant — no row of the collection.
    $this->plainPage = Page::factory()->create([
        'tenant_id' => $this->tenantId,
        'name' => ['de' => 'Plain'],
        'data' => ['secret' => 'keep'],
    ]);
});

function zzRowDetail(object $test, mixed $modelId): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test('cms::element-collection-row-detail', [
        'modelId' => $modelId,
        'collectionKey' => $test->elementCollection->collection_key,
    ]);
}

it('refuses a user without the CMS app — mounted directly and through the modal stack', function (): void {
    $outsider = createNoerdUserForApp('ZZOTHER');
    $this->actingAs($outsider, NoerdAuth::guardName());

    zzRowDetail($this, $this->row->id)->assertForbidden();

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', modalComponent: 'cms::element-collection-row-detail', arguments: [
            'modelId' => $this->row->id,
            'collectionKey' => $this->elementCollection->collection_key,
        ])
        ->assertForbidden();
});

it('stores, copies and deletes nothing once the user lost the CMS app', function (): void {
    $component = zzRowDetail($this, $this->row->id)->set('detailData.text', 'Changed');

    $outsider = createNoerdUserForApp('ZZOTHER');
    $this->actingAs($outsider, NoerdAuth::guardName());

    $component->call('store')->call('copy')->call('delete');

    expect($this->row->fresh())->not->toBeNull()
        ->and($this->row->fresh()->data['text'])->toBe('Row one')
        ->and(Page::query()->acrossTenants()->where('collection_id', $this->elementCollection->id)->count())->toBe(1);
});

it('never moves, overwrites or deletes a page that is no row of the collection', function (): void {
    $component = zzRowDetail($this, $this->plainPage->id);

    $component->assertSet('collectionKey', null)
        ->set('detailData.text', 'Injected')
        ->call('store')
        ->call('copy')
        ->call('delete');

    $plain = $this->plainPage->fresh();

    expect($plain)->not->toBeNull()
        ->and($plain->collection_id)->toBeNull()
        ->and($plain->data)->toBe(['secret' => 'keep']);
});

it('opens nothing for an element collection of another tenant', function (): void {
    $foreignTenant = Tenant::factory()->create();
    $foreignOwner = Page::factory()->create(['tenant_id' => $foreignTenant->id]);
    $foreignCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $foreignOwner->id,
        $foreignTenant->id,
        'items',
        [['text' => 'Foreign row']],
        [['name' => 'text', 'label' => 'Text', 'type' => 'text', 'colspan' => 6]],
        'Foreign',
    );
    $foreignRow = Page::query()->acrossTenants()->where('collection_id', $foreignCollection->id)->first();

    Livewire::test('cms::element-collection-row-detail', ['modelId' => null, 'collectionKey' => $foreignCollection->collection_key])
        ->assertSet('collectionKey', null)
        ->set('detailData.text', 'Planted')
        ->call('store');

    expect(Page::query()->acrossTenants()->where('collection_id', $foreignCollection->id)->pluck('id')->all())->toBe([$foreignRow->id]);
});

it('keeps collection key and row schema on the server', function (): void {
    $component = zzRowDetail($this, $this->row->id);

    expect(fn() => $component->set('collectionLayout', ['fields' => [['name' => 'detailData.content_id']]]))
        ->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn() => $component->set('collectionKey', 'ANOTHER'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $component->set('detailData.collection_id', 999)
        ->set('detailData.text', 'Edited')
        ->call('store');

    expect($this->row->fresh()->data)->toBe(['text' => 'Edited', 'logo' => ''])
        ->and($this->row->fresh()->collection_id)->toBe($this->elementCollection->id);
});

it('creates no record when the row id no longer resolves', function (): void {
    $component = zzRowDetail($this, $this->row->id)->set('detailData.text', 'Ghost');
    $this->row->delete();

    $component->call('store');

    expect(Page::query()->acrossTenants()->where('collection_id', $this->elementCollection->id)->count())->toBe(0);
});

it('uploads an image only into an image field of the row schema and stores the media id', function (): void {
    Storage::fake('media');

    $component = zzRowDetail($this, $this->row->id);

    $component->set('imageUploads.text', UploadedFile::fake()->image('a.jpg'))
        ->assertSet('detailData.text', 'Row one')
        ->set('imageUploads.logo', UploadedFile::fake()->create('logo.pdf', 5, 'application/pdf'))
        ->assertHasErrors(['imageUploads.logo'])
        ->set('imageUploads.logo', UploadedFile::fake()->image('logo.png'))
        ->assertSet('detailData.logo', fn($value): bool => is_int($value) && Media::whereKey($value)->exists())
        ->call('store');

    expect($this->row->fresh()->data['logo'])->toBe(Media::query()->value('id'));
});
