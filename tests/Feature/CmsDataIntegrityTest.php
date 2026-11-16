<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\CollectionEntryStore;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Services\PageCopyService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->rowFields = [['name' => 'title', 'label' => 'Title', 'type' => 'translatableText', 'colspan' => 12]];
});

/**
 * A page with one element that owns an element collection (a slider) of two rows.
 *
 * @return array{page: Page, element: ContentElement, collection: Collection}
 */
function zzPageWithSlider(object $test): array
{
    $page = Page::factory()->create(['tenant_id' => $test->tenantId, 'name' => ['en' => 'Home'], 'slug' => ['en' => '/home']]);
    $element = ContentElement::create(['content_id' => $page->content_id, 'element_key' => 'slider', 'sort' => 1, 'data' => []]);
    $collection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_ELEMENT_PAGE,
        $element->id,
        $test->tenantId,
        'slides',
        [['title' => ['en' => 'First']], ['title' => ['en' => 'Second']]],
        $test->rowFields,
        'Slides',
    );

    return ['page' => $page, 'element' => $element, 'collection' => $collection];
}

function zzRowTitles(Collection $collection): array
{
    return Page::query()->withoutGlobalScopes()->where('collection_id', $collection->id)->orderBy('sort')->get()
        ->map(fn(Page $row): string => $row->data['title']['en'] ?? '')->all();
}

it('deletes the contents of the entries with the collection', function (): void {
    $collection = Collection::create(['tenant_id' => $this->tenantId, 'collection_key' => 'NEWS', 'name' => 'News']);
    $entry = Page::factory()->create(['tenant_id' => $this->tenantId, 'collection_id' => $collection->id, 'name' => ['en' => 'Entry']]);
    ContentElement::create(['content_id' => $entry->content_id, 'element_key' => 'rich_text', 'sort' => 1, 'data' => []]);
    $contentId = $entry->content_id;

    $collection->delete();

    expect(Page::query()->withoutGlobalScopes()->find($entry->id))->toBeNull()
        ->and(Content::query()->withoutGlobalScopes()->find($contentId))->toBeNull()
        ->and(ContentElement::query()->where('content_id', $contentId)->exists())->toBeFalse();
});

it('deletes the element collections and their row contents with a page', function (): void {
    ['page' => $page, 'collection' => $collection] = zzPageWithSlider($this);
    $rowContents = Page::query()->withoutGlobalScopes()->where('collection_id', $collection->id)->pluck('content_id')->all();

    $page->delete();

    expect(Collection::query()->withoutGlobalScopes()->find($collection->id))->toBeNull()
        ->and(Content::query()->withoutGlobalScopes()->whereIn('id', $rowContents)->exists())->toBeFalse();
});

it('copies the element collections of a page with their rows', function (): void {
    ['page' => $page, 'collection' => $original] = zzPageWithSlider($this);

    $copy = app(PageCopyService::class)->copy($page);
    $copiedElement = $copy->elements()->first();
    $copied = Collection::query()->where('element_page_id', $copiedElement->id)->first();

    expect($copied)->not->toBeNull()
        ->and($copied->id)->not->toBe($original->id)
        ->and(zzRowTitles($copied))->toBe(['First', 'Second'])
        // The original keeps its own rows — deleting the copy does not touch them.
        ->and(zzRowTitles($original))->toBe(['First', 'Second']);

    $copy->delete();

    expect(zzRowTitles($original->fresh()))->toBe(['First', 'Second']);
});

it('copies the element collections of a duplicated row', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Owner']]);
    $outer = app(ElementCollectionService::class)->importItems(ElementCollectionService::OWNER_PAGE, $owner->id, $this->tenantId, 'cards', [['title' => ['en' => 'Card']]], $this->rowFields, 'Cards');
    $row = $outer->rows()->first();
    $inner = app(ElementCollectionService::class)->importItems(ElementCollectionService::OWNER_PAGE, $row->id, $this->tenantId, 'bullets', [['title' => ['en' => 'Bullet']]], $this->rowFields, 'Bullets');

    $copy = app(CollectionEntryStore::class)->duplicateRow($row);
    $copiedInner = Collection::query()->where('page_id', $copy->id)->first();

    expect($copiedInner)->not->toBeNull()
        ->and($copiedInner->id)->not->toBe($inner->id)
        ->and(zzRowTitles($copiedInner))->toBe(['Bullet']);
});

it('reports orphaned page contents and deletes them only with --force', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Kept']]);
    $orphan = Content::query()->withoutGlobalScopes()->create(['tenant_id' => $this->tenantId, 'type' => 'cms.page', 'name' => ['en' => 'Orphan']]);
    ContentElement::create(['content_id' => $orphan->id, 'element_key' => 'rich_text', 'sort' => 1, 'data' => []]);
    // A content type of another module is never the CMS's business.
    $foreignType = Content::query()->withoutGlobalScopes()->create(['tenant_id' => $this->tenantId, 'type' => 'zz.other', 'name' => ['en' => 'Other']]);

    Artisan::call('cms:prune-orphaned-contents');

    expect(Content::query()->withoutGlobalScopes()->find($orphan->id))->not->toBeNull();

    Artisan::call('cms:prune-orphaned-contents', ['--force' => true]);

    expect(Content::query()->withoutGlobalScopes()->find($orphan->id))->toBeNull()
        ->and(ContentElement::query()->where('content_id', $orphan->id)->exists())->toBeFalse()
        ->and(Content::query()->withoutGlobalScopes()->find($foreignType->id))->not->toBeNull()
        ->and(Content::query()->withoutGlobalScopes()->find($page->content_id))->not->toBeNull();
});
