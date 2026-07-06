<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Media\Models\Media as MediaModel;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);
    DatabaseCollectionDefinitionRepository::resetCache();
});

/**
 * Seed a definition plus its parent collection row for the current tenant.
 *
 * @param  array<int, array<string, mixed>>  $fields
 */
function pageDetailDefinition(int $tenantId, string $filename, array $fields, bool $hasPage = true): Collection
{
    $key = mb_strtoupper(str_replace('-', '_', $filename));

    CollectionDefinition::create([
        'tenant_id' => $tenantId,
        'filename' => $filename,
        'key' => $key,
        'title' => ucfirst($filename),
        'title_list' => ucfirst($filename),
        'has_page' => $hasPage,
        'fields' => $fields,
    ]);

    return Collection::create([
        'tenant_id' => $tenantId,
        'collection_key' => $key,
        'name' => ucfirst($filename),
    ]);
}

it('uploads an image via images.field binding and stores path into model', function (): void {
    Storage::fake('media');

    $collection = pageDetailDefinition($this->tenant->id, 'projects', [
        ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ['name' => 'detailData.image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
    ]);

    $entry = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => [],
        'sort' => 0,
    ]);

    $before = MediaModel::count();

    Livewire::test('page-detail', ['pageId' => $entry->id, 'collectionKey' => 'projects'])
        ->set('images.image', UploadedFile::fake()->image('photo.jpg', 1200, 800))
        ->assertSet('detailData.image', fn ($value) => is_string($value) && $value !== '');

    expect(MediaModel::count())->toBe($before + 1);

    $media = MediaModel::latest('id')->first();
    expect($media->tenant_id)->toBe($this->tenant->id)
        ->and($media->disk)->toBe('media')
        ->and($media->name)->toBe('photo.jpg')
        ->and($media->extension)->toBe('jpg')
        ->and($media->path)->not->toBe('')
        ->and($media->thumbnail)->not->toBeNull();
});

it('deletes an image value from model', function (): void {
    $collection = pageDetailDefinition($this->tenant->id, 'projects', [
        ['name' => 'detailData.image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
    ]);

    $entry = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => ['image' => '/storage/uploads/any.jpg'],
        'sort' => 0,
    ]);

    Livewire::test('page-detail', ['pageId' => $entry->id, 'collectionKey' => 'projects'])
        ->call('deleteImage', 'image')
        ->assertSet('detailData.image', null);
});

it('does not update image on mediaSelected when token mismatches; updates when token matches', function (): void {
    Storage::fake('media');

    $collection = pageDetailDefinition($this->tenant->id, 'projects', [
        ['name' => 'detailData.image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
    ]);

    $entry = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => [],
        'sort' => 0,
    ]);

    $path = $this->tenant->id.'/test-select.jpg';
    Storage::disk('media')->put($path, 'x');
    $media = MediaModel::create([
        'tenant_id' => $this->tenant->id,
        'type' => 'image',
        'name' => 'test-select.jpg',
        'extension' => 'jpg',
        'path' => $path,
        'disk' => 'media',
        'size' => 1,
    ]);

    $component = Livewire::test('page-detail', ['pageId' => $entry->id, 'collectionKey' => 'projects'])
        ->set('detailData.__mediaToken', 'token-abc')
        ->set('detailData.image', 'UNCHANGED');

    $component->call('mediaSelected', $media->id, 'image', 'wrong-token')
        ->assertSet('detailData.image', 'UNCHANGED');

    $component->call('mediaSelected', $media->id, 'image', 'token-abc')
        ->assertSet('detailData.image', fn ($value) => is_string($value) && $value !== '' && $value !== 'UNCHANGED');
});

it('handles collections without page features (hasPage: false)', function (): void {
    $collection = pageDetailDefinition($this->tenant->id, 'customers', [
        ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ['name' => 'detailData.description', 'label' => 'Description', 'type' => 'translatableText', 'colspan' => 6],
    ], hasPage: false);

    Livewire::test('page-detail', ['collectionKey' => 'customers'])
        ->assertSet('collectionKey', 'customers')
        ->assertSet('collectionLayout.hasPage', false)
        ->assertSet('hasPageFeatures', false)
        ->set('detailData.name', ['de' => 'Test Kunde', 'en' => 'Test Customer'])
        ->set('detailData.description', ['de' => 'Test Beschreibung', 'en' => 'Test Description'])
        ->call('store')
        ->assertHasNoErrors();

    $page = Page::latest('id')->first();
    expect($page->collection_id)->toBe($collection->id)
        ->and($page->name)->toBeNull()
        ->and($page->slug)->toBeNull()
        ->and($page->data)->toHaveKeys(['name', 'description']);
});

it('creates a page with generated, uniquified slug when the definition has hasPage: true', function (): void {
    $collection = pageDetailDefinition($this->tenant->id, 'contacts', [
        ['name' => 'detailData.title', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
    ]);

    Livewire::test('page-detail', ['collectionKey' => 'contacts'])
        ->assertSet('hasPageFeatures', true)
        ->set('detailData.name.en', 'Max Mustermann')
        ->call('store')
        ->assertHasNoErrors();

    $first = Page::where('collection_id', $collection->id)->latest('id')->first();
    expect($first->name['en'])->toBe('Max Mustermann')
        ->and($first->slug['en'])->toContain('max-mustermann')
        ->and($first->is_active)->toBeTruthy();

    Livewire::test('page-detail', ['collectionKey' => 'contacts'])
        ->set('detailData.name.en', 'Max Mustermann')
        ->call('store')
        ->assertHasNoErrors();

    $second = Page::where('collection_id', $collection->id)->latest('id')->first();
    expect($second->id)->not->toBe($first->id)
        ->and($second->slug['en'])->toContain('max-mustermann')
        ->and($second->slug['en'])->not->toBe($first->slug['en']);
});

it('requires a name for the default language when the definition has hasPage: true', function (): void {
    pageDetailDefinition($this->tenant->id, 'contacts', [
        ['name' => 'detailData.title', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
    ]);

    Livewire::test('page-detail', ['collectionKey' => 'contacts'])
        ->call('store')
        ->assertHasErrors();

    expect(Page::whereNotNull('collection_id')->count())->toBe(0);
});

it('preserves manually edited slug when saving collection page', function (): void {
    $collection = pageDetailDefinition($this->tenant->id, 'mitarbeiter', [
        ['name' => 'detailData.title', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
        ['name' => 'detailData.title2', 'label' => 'Titel', 'type' => 'text', 'colspan' => 6],
    ]);

    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'name' => ['en' => 'Gerit Woerner'],
        'slug' => ['en' => '/gerit-woerner'],
        'data' => ['title' => 'Gerit Woerner', 'title2' => 'Steuerberater'],
        'layout' => 'weblayout',
    ]);

    Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail', ['collectionKey' => 'mitarbeiter'])
        ->set('detailData.slug.en', '/gerit-woerner-updated')
        ->call('store')
        ->assertOk();

    expect(Page::find($page->id)->slug)->toBe(['en' => '/gerit-woerner-updated']);
});

it('stores only collection-specific fields in data column', function (): void {
    $collection = pageDetailDefinition($this->tenant->id, 'mitarbeiter', [
        ['name' => 'detailData.title', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
        ['name' => 'detailData.title2', 'label' => 'Titel', 'type' => 'text', 'colspan' => 6],
        ['name' => 'detailData.phone', 'label' => 'Telefon', 'type' => 'text', 'colspan' => 6],
        ['name' => 'detailData.email', 'label' => 'E-Mail', 'type' => 'text', 'colspan' => 6],
    ]);

    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'name' => ['en' => 'Test Person'],
        'slug' => ['en' => '/test-person'],
        'data' => ['title' => 'Old Title'],
        'layout' => 'weblayout',
    ]);

    Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail', ['collectionKey' => 'mitarbeiter'])
        ->set('detailData.title', 'New Title')
        ->set('detailData.title2', 'New Subtitle')
        ->set('detailData.phone', '+49 123 456')
        ->set('detailData.email', 'test@example.com')
        ->call('store')
        ->assertOk();

    $data = Page::find($page->id)->data;

    expect($data)->toHaveKeys(['title', 'title2', 'phone', 'email'])
        ->and($data)->not->toHaveKey('name')
        ->and($data)->not->toHaveKey('slug')
        ->and($data)->not->toHaveKey('layout')
        ->and($data)->not->toHaveKey('is_active')
        ->and($data)->not->toHaveKey('tenant_id')
        ->and($data)->not->toHaveKey('collection_id');
});

it('does not overwrite slug column or core page fields from stale data on mount', function (): void {
    $collection = pageDetailDefinition($this->tenant->id, 'mitarbeiter', [
        ['name' => 'detailData.title', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
    ]);

    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'name' => ['en' => 'Correct Name'],
        'slug' => ['en' => '/correct-slug'],
        'layout' => 'correct-layout',
        'sort' => 5,
        'data' => [
            'title' => 'Collection Field',
            'name' => ['en' => 'Stale Name'],
            'slug' => ['en' => '/stale-slug'],
            'layout' => 'stale-layout',
            'sort' => 99,
        ],
    ]);

    $detailData = Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail', ['collectionKey' => 'mitarbeiter'])
        ->get('detailData');

    expect($detailData['name']['en'])->toBe('Correct Name')
        ->and($detailData['slug']['en'])->toBe('/correct-slug')
        ->and($detailData['layout'])->toBe('correct-layout')
        ->and($detailData['sort'])->toBe(5)
        ->and($detailData['title'])->toBe('Collection Field');
});

it('preserves element-collection array data when saving a collection page', function (): void {
    $collection = pageDetailDefinition($this->tenant->id, 'services', [
        ['name' => 'detailData.title', 'label' => 'Titel', 'type' => 'translatableText', 'colspan' => 6],
        [
            'name' => 'detailData.activities_items',
            'label' => 'Activities',
            'type' => 'element-collection',
            'fields' => [
                ['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea'],
            ],
        ],
    ]);

    $items = [
        ['text' => ['de' => 'Analyse', 'en' => 'Analysis']],
        ['text' => ['de' => 'Konzeption', 'en' => 'Concept']],
    ];

    $page = Page::create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => [
            'title' => ['de' => 'Beratung', 'en' => 'Consulting'],
            'activities_items' => $items,
        ],
        'sort' => 1,
    ]);

    expect($page->fresh()->data['activities_items'])->toEqual($items);
});

it('stores entries under the definition key even when the URL key is hyphenated', function (): void {
    $collection = pageDetailDefinition($this->tenant->id, 'team-members', [
        ['name' => 'detailData.title', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
    ], hasPage: false);

    expect($collection->collection_key)->toBe('TEAM_MEMBERS');

    Livewire::test('page-detail', ['collectionKey' => 'team-members'])
        ->set('detailData.title', 'Erster Eintrag')
        ->call('store')
        ->assertHasNoErrors();

    expect(Collection::query()->where('collection_key', 'TEAM-MEMBERS')->exists())->toBeFalse()
        ->and(Collection::query()->where('collection_key', 'TEAM_MEMBERS')->count())->toBe(1)
        ->and(Page::where('collection_id', $collection->id)->count())->toBe(1);
});
