<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Cms\Exceptions\ForeignContentException;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesCollectionDefinitions;
use Noerd\Media\Models\Media;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesCollectionDefinitions::class);

/*
 | The page editor acts on client input from the browser: these tests send what
 | an attacker with write permission on pages would send and prove that no
 | field outside the server-side layout is written and no content of another
 | tenant or record is reached.
 */
beforeEach(function (): void {
    $this->actingAsCmsUser();

    $this->foreignTenant = Tenant::factory()->create();
    $this->foreignPage = Page::factory()->create(['tenant_id' => $this->foreignTenant->id, 'name' => ['en' => 'Foreign']]);
    $this->foreignElement = ContentElement::create([
        'content_id' => $this->foreignPage->content_id,
        'element_key' => 'rich_text',
        'sort' => 1,
        'data' => ['text' => ['en' => '<p>Foreign text</p>']],
    ]);

    $this->ownPage = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Own'], 'slug' => ['en' => '/own']]);
});

/**
 * The foreign content and its element are exactly as the fixture created them.
 */
function zzAssertForeignContentUntouched(object $test): void
{
    $content = Content::query()->withoutGlobalScopes()->find($test->foreignPage->content_id);

    expect($content->name)->toBe(['en' => 'Foreign'])
        ->and(ContentElement::query()->where('content_id', $content->id)->pluck('id')->all())->toBe([$test->foreignElement->id]);
}

it('ignores a pageSelected event whose context names a field the layout does not declare', function (): void {
    // A page of the own tenant whose id differs from every id of the edited page.
    $picked = Page::factory()->create(['tenant_id' => $this->tenantId]);
    $component = Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail');

    foreach (['content_id', 'detailData.content_id', 'tenant_id', 'id', 'collection_id'] as $context) {
        $component->dispatch('pageSelected', $picked->id, $context);
    }

    $component->assertSet('detailData.content_id', $this->ownPage->content_id)
        ->assertSet('detailData.tenant_id', $this->tenantId)
        ->assertSet('detailData.id', $this->ownPage->id)
        ->set('detailData.name.en', 'Renamed')
        ->call('store')
        ->assertHasNoErrors();

    expect($this->ownPage->fresh()->content_id)->toBe($this->ownPage->content_id)
        ->and($this->ownPage->fresh()->name['en'])->toBe('Renamed');
    zzAssertForeignContentUntouched($this);
});

it('writes a picked page only into a page relation field of the collection layout', function (): void {
    $collection = $this->zzCollectionDefinition($this->tenantId, 'teasers', [
        ['name' => 'detailData.target', 'label' => 'Target', 'type' => 'pageRelation', 'colspan' => 6],
        ['name' => 'detailData.headline', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
    ], hasPage: false);
    $entry = Page::factory()->create(['tenant_id' => $this->tenantId, 'collection_id' => $collection->id, 'data' => []]);

    Livewire::withUrlParams(['pageId' => $entry->id])->test('cms::page-detail')
        ->dispatch('pageSelected', $this->ownPage->id, 'detailData.headline')
        ->assertSet('detailData.headline', null)
        ->dispatch('pageSelected', $this->foreignPage->id, 'detailData.target')
        ->assertSet('detailData.target', null)
        ->dispatch('pageSelected', $this->ownPage->id, 'detailData.target')
        ->assertSet('detailData.target', $this->ownPage->id);
});

it('never takes the collection key or the collection layout from the client', function (): void {
    $component = Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail');

    expect(fn() => $component->set('collectionLayout', ['fields' => [['name' => 'detailData.content_id', 'type' => 'text']]]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn() => $component->set('collectionKey', 'anything'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('edits the collection an existing page belongs to, whatever the opener passed', function (): void {
    $news = $this->zzCollectionDefinition($this->tenantId, 'news', [
        ['name' => 'detailData.headline', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
    ], hasPage: false);
    $this->zzCollectionDefinition($this->tenantId, 'other', [
        ['name' => 'detailData.content_id', 'label' => 'Evil', 'type' => 'text', 'colspan' => 6],
    ], hasPage: false);

    $plain = Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail', ['collectionKey' => 'other']);
    $plain->assertSet('collectionKey', null);

    $entry = Page::factory()->create(['tenant_id' => $this->tenantId, 'collection_id' => $news->id, 'data' => []]);

    Livewire::withUrlParams(['pageId' => $entry->id])->test('cms::page-detail', ['collectionKey' => 'other'])
        ->assertSet('collectionKey', 'news')
        ->set('detailData.content_id', $this->foreignPage->content_id)
        ->call('store');

    expect($entry->fresh()->collection_id)->toBe($news->id)
        ->and($entry->fresh()->content_id)->toBe($entry->content_id);
    zzAssertForeignContentUntouched($this);
});

it('stores no content, tenant or id the client put into detailData', function (): void {
    Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail')
        ->set('detailData.content_id', $this->foreignPage->content_id)
        ->set('detailData.tenant_id', $this->foreignTenant->id)
        ->set('detailData.id', $this->foreignPage->id)
        ->set('detailData.collection_id', 999)
        ->set('detailData.name.en', 'Still own')
        ->call('store')
        ->assertHasNoErrors();

    $page = $this->ownPage->fresh();

    expect($page->content_id)->toBe($this->ownPage->content_id)
        ->and($page->tenant_id)->toBe($this->tenantId)
        ->and($page->collection_id)->toBeNull()
        ->and($page->name['en'])->toBe('Still own');
    zzAssertForeignContentUntouched($this);
});

it('stores nothing for a page id the tenant scope cannot resolve', function (): void {
    $component = Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail')
        ->set('detailData.name.en', 'Ghost');

    $this->ownPage->delete();
    $count = Page::query()->acrossTenants()->count();

    $component->call('store');

    expect(Page::query()->acrossTenants()->count())->toBe($count);
});

it('refuses a layout that is not one of the discovered layouts', function (): void {
    Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail')
        ->set('detailData.layout', '../../../../etc/passwd')
        ->call('store')
        ->assertHasErrors(['detailData.layout']);

    expect($this->ownPage->fresh()->layout)->not->toBe('../../../../etc/passwd');
});

it('writes a picked or uploaded image only into an image field of the layout', function (): void {
    Storage::fake('media');
    $media = Media::factory()->create(['tenant_id' => $this->tenantId, 'disk' => 'media', 'path' => 'a.jpg']);
    $foreignMedia = Media::factory()->create(['tenant_id' => $this->foreignTenant->id, 'disk' => 'media', 'path' => 'b.jpg']);

    $component = Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail');

    // No picker opens for a field that is no image field — so no token.
    $component->call('openSelectMediaModal', 'content_id')
        ->assertSet('mediaSelectToken', null)
        ->assertNotDispatched('noerdModal');

    $component->call('openSelectMediaModal', 'og_image');
    $token = $component->get('mediaSelectToken');

    $component->call('mediaSelected', $media->id, 'content_id', $token)
        ->assertSet('detailData.content_id', $this->ownPage->content_id)
        ->call('mediaSelected', $foreignMedia->id, 'og_image', $token)
        ->assertSet('detailData.og_image', null)
        ->call('mediaSelected', $media->id, 'og_image', $token)
        ->assertSet('detailData.og_image', $media->id)
        ->call('deleteImage', 'content_id')
        ->assertSet('detailData.content_id', $this->ownPage->content_id);

    // An upload into a field that is no image field is dropped.
    $component->set('imageUploads.content_id', UploadedFile::fake()->image('x.jpg'))
        ->assertSet('detailData.content_id', $this->ownPage->content_id);
});

it('refuses an upload that is no image and stores a valid one as media id', function (): void {
    Storage::fake('media');

    $component = Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail');

    $component->set('imageUploads.og_image', UploadedFile::fake()->create('evil.php', 10, 'application/x-php'))
        ->assertHasErrors(['imageUploads.og_image']);

    expect(Media::count())->toBe(0);

    $component->set('imageUploads.og_image', UploadedFile::fake()->image('cover.jpg', 600, 400))
        ->assertSet('detailData.og_image', fn($value): bool => is_int($value) && Media::whereKey($value)->exists());
});

it('refuses an image upload for a user who may not save the page', function (): void {
    Storage::fake('media');
    $this->actingAsCmsUser(Noerd\Enums\Profile::ReadOnly);
    $page = Page::factory()->create(['tenant_id' => $this->tenantId]);

    Livewire::withUrlParams(['pageId' => $page->id])->test('cms::page-detail')
        ->set('imageUploads.og_image', UploadedFile::fake()->image('cover.jpg'))
        ->assertSet('detailData.og_image', null);

    expect(Media::count())->toBe(0);
});

describe('ExtendsContent', function (): void {
    it('refuses to create a record on the content of another tenant', function (): void {
        expect(fn() => Page::create(['tenant_id' => $this->tenantId, 'content_id' => $this->foreignPage->content_id]))
            ->toThrow(ForeignContentException::class);
    });

    it('refuses to create a record on a content another record extends or of another type', function (): void {
        $otherType = Content::factory()->create(['tenant_id' => $this->tenantId, 'type' => 'zz.other']);

        expect(fn() => Page::create(['tenant_id' => $this->tenantId, 'content_id' => $this->ownPage->content_id]))
            ->toThrow(ForeignContentException::class)
            ->and(fn() => Page::create(['tenant_id' => $this->tenantId, 'content_id' => $otherType->id]))
            ->toThrow(ForeignContentException::class);
    });

    it('adopts a free content of its own tenant and type', function (): void {
        $free = Content::factory()->create(['tenant_id' => $this->tenantId, 'type' => Page::contentType(), 'name' => ['en' => 'Free']]);

        $page = Page::create(['tenant_id' => $this->tenantId, 'content_id' => $free->id]);

        expect($page->content_id)->toBe($free->id)
            ->and($page->name)->toBe(['en' => 'Free']);
    });

    it('refuses to swap the content of a saved record', function (): void {
        $free = Content::factory()->create(['tenant_id' => $this->tenantId, 'type' => Page::contentType()]);
        $ownContentId = $this->ownPage->content_id;

        expect(fn() => $this->ownPage->fresh()->update(['content_id' => $this->foreignPage->content_id]))
            ->toThrow(ForeignContentException::class)
            ->and(fn() => $this->ownPage->fresh()->update(['content_id' => $free->id]))
            ->toThrow(ForeignContentException::class);

        expect($this->ownPage->fresh()->content_id)->toBe($ownContentId);
    });

    it('writes the name only into the content of the record tenant', function (): void {
        // A row whose content_id was pointed at a foreign content by hand.
        $foreignContent = Content::factory()->create(['tenant_id' => $this->foreignTenant->id, 'type' => Page::contentType(), 'name' => ['en' => 'Foreign orphan']]);
        Page::query()->withoutGlobalScopes()->whereKey($this->ownPage->id)->update(['content_id' => $foreignContent->id]);

        $page = Page::query()->find($this->ownPage->id);
        $page->name = ['en' => 'Overwritten'];
        $page->save();

        expect($page->ownContent())->toBeNull()
            ->and(Content::query()->withoutGlobalScopes()->find($foreignContent->id)->name)->toBe(['en' => 'Foreign orphan']);
    });
});

describe('persistContentDocument', function (): void {
    it('refuses to write the document into a content that is not the record\'s own', function (): void {
        $component = Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail');
        $foreignContent = Content::query()->withoutGlobalScopes()->find($this->foreignPage->content_id);
        $siblingPage = Page::factory()->create(['tenant_id' => $this->tenantId]);

        expect(fn() => $component->instance()->persistContentDocument($foreignContent, $this->ownPage))
            ->toThrow(ForeignContentException::class)
            ->and(fn() => $component->instance()->persistContentDocument($foreignContent))
            ->toThrow(ForeignContentException::class)
            ->and(fn() => $component->instance()->persistContentDocument($siblingPage->ownContent()))
            ->toThrow(ForeignContentException::class);

        zzAssertForeignContentUntouched($this);
    });

    it('refuses a content id the client sends to the action', function (): void {
        $siblingPage = Page::factory()->create(['tenant_id' => $this->tenantId]);
        $sibling = ContentElement::create(['content_id' => $siblingPage->content_id, 'element_key' => 'rich_text', 'sort' => 1, 'data' => ['text' => ['en' => '<p>Sibling</p>']]]);

        expect(fn() => Livewire::withUrlParams(['pageId' => $this->ownPage->id])->test('cms::page-detail')
            ->call('persistContentDocument', $siblingPage->content_id))
            ->toThrow(ForeignContentException::class);

        expect($sibling->fresh())->not->toBeNull();
    });
});
