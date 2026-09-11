<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\PageElementService;
use Noerd\Cms\Support\MediaValues;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;
use Noerd\Media\Models\Media;
use Noerd\Media\Models\MediaFolder;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

beforeEach(function (): void {
    Storage::fake('media');
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);
    $this->tenantId = (int) $this->tenant->id;
});

/** The field definition of an element that carries one image field. */
function zzImageFields(): array
{
    return [
        ['name' => 'detailData.headline', 'label' => 'Headline', 'type' => 'text'],
        ['name' => 'image', 'label' => 'Image', 'type' => 'image'],
    ];
}

it('turns a stored media id into a URL', function (): void {
    $media = Media::factory()->file($this->tenantId, 'logo.svg')->create();

    $resolved = MediaValues::resolve(['image' => $media->id], zzImageFields());

    expect($resolved['image'])->toBeString()->toContain('logo.svg');
});

it('follows a file that moved to another folder', function (): void {
    $folder = MediaFolder::create(['tenant_id' => $this->tenantId, 'name' => 'Presse']);
    $media = Media::factory()->file($this->tenantId, 'logo.svg', $folder->id)->create();

    $resolved = MediaValues::resolve(['image' => $media->id], zzImageFields());

    // The stored value never contained a path, so the folder shows up here.
    expect($resolved['image'])->toContain('Presse/logo.svg');
});

it('leaves a legacy URL string untouched', function (): void {
    $resolved = MediaValues::resolve(['image' => '/storage/media/1/logo.svg'], zzImageFields());

    expect($resolved['image'])->toBe('/storage/media/1/logo.svg');
});

it('leaves values of fields that are not images untouched', function (): void {
    $resolved = MediaValues::resolve(['headline' => '42'], zzImageFields());

    expect($resolved['headline'])->toBe('42');
});

it('leaves an image field empty when it is empty', function (): void {
    $resolved = MediaValues::resolve(['image' => null], zzImageFields());

    expect($resolved['image'])->toBeNull();
});

it('keeps an id that no longer resolves to a file', function (): void {
    $resolved = MediaValues::resolve(['image' => 999999], zzImageFields());

    expect($resolved['image'])->toBe(999999);
});

it('resolves an element image field when a page is rendered', function (): void {
    $this->createElementFixtures();

    try {
        $media = Media::factory()->file($this->tenantId, 'logo.svg')->create();

        $page = Page::factory()->create(['tenant_id' => $this->tenantId]);
        ElementPage::factory()->create([
            'page_id' => $page->id,
            'element_key' => $this->zzImageElementKey(),
            'data' => ['image' => $media->id, 'headline' => 'Hello'],
        ]);

        $elements = app(PageElementService::class)->processPageElements($page->fresh(), 'de');

        // The element Blade keeps reading a plain URL — the id never reaches it.
        expect($elements)->toHaveCount(1)
            ->and($elements[0]['data']->image)->toBeString()->toContain('logo.svg')
            ->and($elements[0]['data']->headline)->toBe('Hello');
    } finally {
        $this->removeElementFixtures();
    }
});
