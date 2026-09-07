<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

$testSettings = [
    'componentName' => 'cms::page-detail',
    'listName' => 'cms::pages-list',
    'id' => 'modelId',
    'urlParam' => 'pageId',
];

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user, NoerdAuth::guardName());
    $this->createElementFixtures();
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('validates the data', function () use ($testSettings): void {
    $user = $this->user;

    // Test with invalid data (empty name array)
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', [])
        ->call('store')
        ->assertHasErrors(['detailData.name']);
});

it('successfully stores the data', function () use ($testSettings): void {
    $user = $this->user;

    $component = Livewire::test($testSettings['componentName'])
        ->set('detailData.name.en', 'Test Page')
        ->set('detailData.name.de', 'Test Seite')
        ->set('detailData.layout', 'weblayout')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('cms_pages', [
        'tenant_id' => $user->selected_tenant_id,
        'name->en' => 'Test Page',
        'name->de' => 'Test Seite',
        'layout' => 'weblayout',
    ]);
});

it('successfully deletes a page', function () use ($testSettings): void {
    $user = $this->user;
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['en' => 'Test Page', 'de' => 'Test Seite'],
        'slug' => ['en' => '/test-page', 'de' => '/test-seite'],
    ]);

    Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->call('delete')
        ->assertDispatched('closeTopModal');

    $this->assertDatabaseMissing('cms_pages', [
        'id' => $model->id,
    ]);
});

it('opens and stores existing page', function () use ($testSettings): void {
    $user = $this->user;
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['en' => 'Old Page', 'de' => 'Alte Seite'],
        'slug' => ['en' => '/old-page', 'de' => '/alte-seite'],
    ]);

    Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->set('detailData.name.en', 'New Page')
        ->set('detailData.name.de', 'Neue Seite')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('cms_pages', [
        'id' => $model->id,
        'name->en' => 'New Page',
        'name->de' => 'Neue Seite',
    ]);
});

it('dispatches table action from pages table', function () use ($testSettings): void {
    $user = $this->user;

    // Test just the listAction method without rendering the full table
    $component = Livewire::test($testSettings['listName']);

    $component->call('listAction', 123)
        ->assertDispatched(
            'noerdModal',
            fn(string $event, array $params): bool => ($params['route'] ?? null) === 'cms.page.detail'
                && ($params['source'] ?? null) === $testSettings['listName']
                && ($params['arguments'] ?? null) === ['modelId' => 123, 'relations' => []],
        );
});

it('copies a page with elements', function () use ($testSettings): void {
    $user = $this->user;
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['en' => 'Original Page', 'de' => 'Original Seite'],
        'slug' => ['en' => '/original-page', 'de' => '/original-seite'],
    ]);

    ElementPage::create([
        'page_id' => $model->id,
        'element_key' => $this->zzTextElementKey(),
        'sort' => 1,
        'data' => ['text' => ['en' => 'Hello']],
    ]);

    $component = Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->call('copy')
        ->assertOk()
        ->assertDispatched('listRefresh');

    $this->assertDatabaseCount('cms_pages', 2);

    $copiedPage = Page::where('id', '!=', $model->id)->first();
    expect($copiedPage->name['en'])->toBe('Original Page 2');
    expect($copiedPage->name['de'])->toBe('Original Seite 2');
    expect($copiedPage->slug['en'])->toContain('/original-page-2');
    expect($copiedPage->slug['de'])->toContain('/original-seite-2');

    $copiedElements = ElementPage::where('page_id', $copiedPage->id)->get();
    expect($copiedElements)->toHaveCount(1);
    expect($copiedElements->first()->getRawOriginal('element_key'))->toBe($this->zzTextElementKey());
});

it('copies a collection page and appends 2 to data title', function () use ($testSettings): void {
    $user = $this->user;

    $collection = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'BENEFITS',
        'name' => 'Benefits',
    ]);

    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_id' => $collection->id,
        'name' => null,
        'slug' => null,
        'data' => ['title' => 'Original Benefit', 'description' => 'Some text'],
    ]);

    $component = Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->call('copy')
        ->assertOk()
        ->assertDispatched('listRefresh');

    $copiedPage = Page::where('id', '!=', $model->id)->first();
    expect($copiedPage->data['title'])->toBe('Original Benefit 2');
    expect($copiedPage->data['description'])->toBe('Some text');
});

/*
 | Slug generation of a NEW page: the tenant runs German as its only (and
 | therefore default) language, so slugs carry no language prefix.
 */
describe('slug generation', function (): void {
    beforeEach(function (): void {
        $this->actingAsCmsUser();
        $this->useOnlyLanguage('de');
    });

    it('appends -2 when slug already exists', function (): void {
        Page::factory()->create([
            'tenant_id' => $this->user->selected_tenant_id,
            'name' => ['de' => 'Test'],
            'slug' => ['de' => '/test'],
        ]);

        $component = Livewire::test('cms::page-detail')
            ->set('detailData.name.de', 'Test');

        $detailData = $component->get('detailData');
        expect($detailData['slug']['de'])->toBe('/test-2');
    });

    it('increments to -3 with multiple duplicates', function (): void {
        Page::factory()->create([
            'tenant_id' => $this->user->selected_tenant_id,
            'name' => ['de' => 'Test'],
            'slug' => ['de' => '/test'],
        ]);

        Page::factory()->create([
            'tenant_id' => $this->user->selected_tenant_id,
            'name' => ['de' => 'Test 2'],
            'slug' => ['de' => '/test-2'],
        ]);

        $component = Livewire::test('cms::page-detail')
            ->set('detailData.name.de', 'Test');

        $detailData = $component->get('detailData');
        expect($detailData['slug']['de'])->toBe('/test-3');
    });

    it('allows same slug for different tenants', function (): void {
        // Create page for a different tenant
        $otherTenant = \Noerd\Models\Tenant::factory()->create();
        Page::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => ['de' => 'Test'],
            'slug' => ['de' => '/test'],
        ]);

        // New page for current tenant should get /test (no conflict)
        $component = Livewire::test('cms::page-detail')
            ->set('detailData.name.de', 'Test');

        $detailData = $component->get('detailData');
        expect($detailData['slug']['de'])->toBe('/test');
    });

    it('does not auto-generate slug after first save', function (): void {
        $page = Page::factory()->create([
            'tenant_id' => $this->user->selected_tenant_id,
            'name' => ['de' => 'Original'],
            'slug' => ['de' => '/original'],
            'layout' => 'weblayout',
        ]);

        // Open existing page and change title
        $component = Livewire::withUrlParams(['pageId' => $page->id])
            ->test('page-detail')
            ->set('detailData.name.de', 'Neuer Titel');

        // Slug should remain unchanged
        $detailData = $component->get('detailData');
        expect($detailData['slug']['de'])->toBe('/original');
    });

    it('allows manual slug editing on saved pages', function (): void {
        $page = Page::factory()->create([
            'tenant_id' => $this->user->selected_tenant_id,
            'name' => ['de' => 'Original'],
            'slug' => ['de' => '/original'],
            'layout' => 'weblayout',
        ]);

        Livewire::withUrlParams(['pageId' => $page->id])
            ->test('page-detail')
            ->set('detailData.slug.de', '/manuell-geaendert')
            ->call('store')
            ->assertOk();

        $updatedPage = Page::find($page->id);
        expect($updatedPage->slug['de'])->toBe('/manuell-geaendert');
    });

    it('updates slug live on new page when title changes', function (): void {
        // Start a new page, type a name (slug auto-generates), then change the name
        $component = Livewire::test('cms::page-detail')
            ->set('detailData.name.de', 'Erster Titel');

        // Slug should be auto-generated
        $detailData = $component->get('detailData');
        expect($detailData['slug']['de'])->toBe('/erster-titel');

        // Change the title — slug should update because page is not yet saved
        $component->set('detailData.name.de', 'Zweiter Titel');
        $detailData = $component->get('detailData');
        expect($detailData['slug']['de'])->toBe('/zweiter-titel');
    });

    describe('language prefix', function (): void {
        /*
         | Slug generation for non-default languages: the default language stays
         | unprefixed, every other active language gets its code as a path prefix,
         | and umlauts transliterate.
         */

        it('prefixes non-default language slugs with the language code', function (): void {
            $this->addLanguage('en', 'English');

            $instance = Livewire::test('cms::page-detail')->instance();

            expect($instance->generateSlug('Über uns', 'de'))->toBe('/ueber-uns')
                ->and($instance->generateSlug('Über uns', 'en'))->toBe('/en/ueber-uns');
        });

        it('keeps the default language unprefixed whatever it is', function (): void {
            // German is the only (default) language of this tenant.
            expect(Livewire::test('cms::page-detail')->instance()->generateSlug('Startseite', 'de'))
                ->toBe('/startseite');
        });
    });
});
