<?php

use Illuminate\Support\Facades\DB;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

// Mock CollectionHelper via Laravel's container
beforeEach(function (): void {
    // Clear any previously set session language (from Pest.php beforeEach or elsewhere)
    session()->forget('selectedLanguage');

    $this->mock(CollectionHelper::class, function ($mock): void {
        // Mock resolveCollectionFields for projects
        $mock->shouldReceive('resolveCollectionFields')
            ->with('projects')
            ->andReturn([
                'title' => 'Project',
                'titleList' => 'Projects',
                'hasPage' => true,
                'fields' => [
                    ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText'],
                    ['name' => 'image', 'label' => 'Image', 'type' => 'image'],
                ],
            ]);

        // Mock resolveCollectionFields for customers
        $mock->shouldReceive('resolveCollectionFields')
            ->with('customers')
            ->andReturn([
                'title' => 'Customer',
                'titleList' => 'Customers',
                'hasPage' => false,
                'fields' => [
                    ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText'],
                    ['name' => 'detailData.description', 'label' => 'Description', 'type' => 'translatableText'],
                ],
            ]);

        // Mock resolveCollectionFields for services (with an element-collection field)
        $mock->shouldReceive('resolveCollectionFields')
            ->with('services')
            ->andReturn([
                'title' => 'Service',
                'titleList' => 'Services',
                'hasPage' => true,
                'fields' => [
                    ['name' => 'detailData.title', 'label' => 'Titel', 'type' => 'translatableText'],
                    [
                        'name' => 'detailData.activities_items',
                        'label' => 'Activities',
                        'type' => 'element-collection',
                        'fields' => [
                            ['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea'],
                        ],
                    ],
                ],
            ]);
    });
});

it('displays dynamic columns from YAML configuration for projects', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Set up German as test language (project-independent)
    CmsLanguage::where('tenant_id', $tenant->id)->delete();
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    // Create a project entry with data matching the mocked fields
    Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Test Projekt',
                'en' => 'Test Project',
            ],
            'image' => '/storage/test-image.jpg',
        ],
        'sort' => 1,
    ]);

    // Test using HTTP request - German values displayed
    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Test Projekt');
    $response->assertSee('✓ Bild vorhanden');
    $response->assertSee('Name');
    $response->assertSee('Image');
    $response->assertSee('Sortierung');
});

it('displays dynamic columns from YAML configuration for customers', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Set up German as test language (project-independent)
    CmsLanguage::where('tenant_id', $tenant->id)->delete();
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'CUSTOMERS',
        'name' => 'Customers',
    ]);

    // Create a customer entry with data matching the mocked fields
    Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Test Kunde',
                'en' => 'Test Customer',
            ],
            'description' => [
                'de' => 'Eine Beschreibung',
                'en' => 'A description',
            ],
        ],
        'sort' => 0,
    ]);

    // Test using HTTP request - German values displayed
    $response = $this->get('/cms/collections?key=customers');
    $response->assertStatus(200);
    $response->assertSee('Test Kunde');
    $response->assertSee('Eine Beschreibung');
    $response->assertSee('Name');
    $response->assertSee('Description');
});

it('handles empty collection entries gracefully', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Set up German as test language (project-independent)
    CmsLanguage::where('tenant_id', $tenant->id)->delete();
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);

    // Test using HTTP request - should show column headers even with no data
    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Name');
    $response->assertSee('Image');
});

it('renders element-collection fields in the list with their row count', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    CmsLanguage::where('tenant_id', $tenant->id)->delete();
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);

    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'SERVICES',
        'name' => 'Services',
    ]);

    $entry = Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => ['title' => ['de' => 'Beratung', 'en' => 'Consulting']],
        'sort' => 1,
    ]);

    // The activities live in a dedicated element collection owned by the entry.
    $service = app(ElementCollectionService::class);
    $elementCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => $service->keyFor(ElementCollectionService::OWNER_PAGE, $entry->id, 'activities_items'),
        'page_id' => $entry->id,
        'is_element_collection' => true,
        'owner_field' => 'activities_items',
        'element_fields' => [['name' => 'detailData.text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'name' => 'Activities Beratung',
    ]);
    foreach ([['de' => 'Analyse'], ['de' => 'Konzeption']] as $sort => $text) {
        // Direct insert to bypass the mocked CollectionHelper in the save hook.
        DB::table('pages')->insert([
            'tenant_id' => $tenant->id,
            'collection_id' => $elementCollection->id,
            'data' => json_encode(['text' => $text]),
            'sort' => $sort,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this->get('/cms/collections?key=services');
    $response->assertStatus(200);
    $response->assertSee('Beratung');
    $response->assertSee('2 Einträge');
});

it('preserves element-collection array data when saving a collection page', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'SERVICES',
        'name' => 'Services',
    ]);

    $items = [
        ['text' => ['de' => 'Analyse', 'en' => 'Analysis']],
        ['text' => ['de' => 'Konzeption', 'en' => 'Concept']],
    ];

    $page = Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'title' => ['de' => 'Beratung', 'en' => 'Consulting'],
            'activities_items' => $items,
        ],
        'sort' => 1,
    ]);

    expect($page->fresh()->data['activities_items'])->toEqual($items);
});

it('displays multiple entries correctly', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Set up German as test language (project-independent)
    CmsLanguage::where('tenant_id', $tenant->id)->delete();
    CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    // Create multiple project entries
    Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Laravel Projekt',
                'en' => 'Laravel Project',
            ],
        ],
        'sort' => 1,
    ]);

    Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Vue.js Anwendung',
                'en' => 'Vue.js Application',
            ],
        ],
        'sort' => 2,
    ]);

    // Test that both entries are visible - German values displayed
    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Laravel Projekt');
    $response->assertSee('Vue.js Anwendung');
});
