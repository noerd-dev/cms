<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\FieldTypeConverter;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('FieldTypeConverter', function (): void {

    beforeEach(function (): void {
        // Create a unique tenant for each test run to avoid collisions
        $tenant = Tenant::factory()->create();
        $this->tenantId = $tenant->id;

        $cmsApp = TenantApp::create([
            'name' => 'CMS_'.uniqid().'_'.getmypid(),
            'title' => 'CMS',
            'icon' => 'cms',
            'route' => 'cms.index',
            'is_active' => true,
        ]);

        $tenant->tenantApps()->attach($cmsApp->id);

        $user = User::factory()->create(['selected_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id);

        $this->actingAs($user);
    });

    afterEach(function (): void {
        // Ensure all Mockery mocks are properly reset after each test
        \Mockery::close();
    });

    // Helper function to create isolated mocks for each test
    function createCollectionHelperMock(): void
    {
        $mock = \Mockery::mock('alias:'.CollectionHelper::class);

        // Default beratung collection
        $mock->shouldReceive('getCollectionFields')
            ->with('beratung')
            ->andReturn([
                'title' => 'Beratung',
                'titleList' => 'Beratungseinträge',
                'key' => 'BERATUNG',
                'buttonList' => 'Neuer Eintrag',
                'description' => '',
                'hasPage' => false,
                'fields' => [
                    ['name' => 'model.title', 'label' => 'Titel', 'type' => 'translatableText', 'colspan' => 6],
                    ['name' => 'model.description', 'label' => 'Beschreibung', 'type' => 'translatableText', 'colspan' => 6],
                    ['name' => 'model.content', 'label' => 'Inhalt', 'type' => 'translatableRichText', 'colspan' => 12],
                ],
            ]);

        // Non-existent collection
        $mock->shouldReceive('getCollectionFields')
            ->with('non_existent_collection')
            ->andReturn(null);

        // Text conversion collection
        $mock->shouldReceive('getCollectionFields')
            ->with('test_text_conversion')
            ->andReturn([
                'fields' => [
                    ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6],
                    ['name' => 'model.description', 'label' => 'Description', 'type' => 'text', 'colspan' => 6],
                ],
            ]);

        // Mixed field types collection
        $mock->shouldReceive('getCollectionFields')
            ->with('test_mixed')
            ->andReturn([
                'fields' => [
                    ['name' => 'model.translatable_field', 'label' => 'Translatable', 'type' => 'translatableText', 'colspan' => 6],
                    ['name' => 'model.text_field', 'label' => 'Text', 'type' => 'text', 'colspan' => 6],
                    ['name' => 'model.number_field', 'label' => 'Number', 'type' => 'number', 'colspan' => 6],
                ],
            ]);

        // Rich text collection
        $mock->shouldReceive('getCollectionFields')
            ->with('test_richtext')
            ->andReturn([
                'fields' => [
                    ['name' => 'model.rich_content', 'label' => 'Rich Content', 'type' => 'translatableRichText', 'colspan' => 12],
                    ['name' => 'model.textarea_content', 'label' => 'Textarea Content', 'type' => 'translatableTextarea', 'colspan' => 12],
                ],
            ]);
    }

    it('converts text fields to translatableText format', function (): void {
        createCollectionHelperMock();

        $originalData = [
            'title' => 'German Title',
            'description' => 'German Description',
            'other_field' => 'Some value',
        ];

        $convertedData = FieldTypeConverter::convertCollectionData($originalData, 'beratung');

        expect($convertedData['title'])->toBeArray()
            ->and($convertedData['title']['de'])->toBe('German Title')
            ->and($convertedData['title']['en'])->toBe('German Title')
            ->and($convertedData['description'])->toBeArray()
            ->and($convertedData['description']['de'])->toBe('German Description')
            ->and($convertedData['description']['en'])->toBe('German Description')
            ->and($convertedData['other_field'])->toBe('Some value'); // Non-collection fields remain unchanged
    });

    it('preserves already correct translatableText format', function (): void {
        createCollectionHelperMock();

        $correctData = [
            'title' => ['de' => 'Deutscher Titel', 'en' => 'English Title'],
            'description' => ['de' => 'Deutsche Beschreibung', 'en' => 'English Description'],
        ];

        $convertedData = FieldTypeConverter::convertCollectionData($correctData, 'beratung');

        expect($convertedData['title'])->toBe($correctData['title'])
            ->and($convertedData['description'])->toBe($correctData['description']);
    });

    it('handles empty and null values gracefully', function (): void {
        createCollectionHelperMock();

        $dataWithEmpties = [
            'title' => '',
            'description' => null,
        ];

        $convertedData = FieldTypeConverter::convertCollectionData($dataWithEmpties, 'beratung');

        expect($convertedData['title'])->toBeArray()
            ->and($convertedData['title']['de'])->toBe('')
            ->and($convertedData['title']['en'])->toBe('')
            ->and($convertedData['description'])->toBeArray()
            ->and($convertedData['description']['de'])->toBe('')
            ->and($convertedData['description']['en'])->toBe('');
    });

    it('returns original data when collection config is missing', function (): void {
        createCollectionHelperMock();

        $originalData = [
            'title' => 'Some Title',
            'description' => 'Some Description',
        ];

        $convertedData = FieldTypeConverter::convertCollectionData($originalData, 'non_existent_collection');

        expect($convertedData)->toBe($originalData);
    });

    it('converts translatableText back to text format', function (): void {
        createCollectionHelperMock();

        $translatableData = [
            'title' => ['de' => 'Deutscher Titel', 'en' => 'English Title'],
            'description' => ['de' => 'Deutsche Beschreibung', 'en' => 'English Description'],
        ];

        $convertedData = FieldTypeConverter::convertCollectionData($translatableData, 'test_text_conversion');

        expect($convertedData['title'])->toBe('Deutscher Titel') // Should use German as primary
            ->and($convertedData['description'])->toBe('Deutsche Beschreibung');
    });

    it('handles mixed field types correctly', function (): void {
        createCollectionHelperMock();

        $originalData = [
            'translatable_field' => 'Should become translatable',
            'text_field' => 'Should stay text',
            'number_field' => 123,
        ];

        $convertedData = FieldTypeConverter::convertCollectionData($originalData, 'test_mixed');

        expect($convertedData['translatable_field'])->toBeArray()
            ->and($convertedData['translatable_field']['de'])->toBe('Should become translatable')
            ->and($convertedData['translatable_field']['en'])->toBe('Should become translatable')
            ->and($convertedData['text_field'])->toBe('Should stay text')
            ->and($convertedData['number_field'])->toBe(123);
    });

    it('automatically converts data when saving Page model', function (): void {
        // Create a collection with unique tenant ID and timestamp to avoid conflicts
        $uniqueSuffix = time().'_'.getmypid();
        $uniqueCollectionKey = 'BERATUNG_'.$uniqueSuffix;

        // Create mock that responds to the lowercase collection key (as per Page model behavior)
        $lowercaseCollectionKey = mb_strtolower($uniqueCollectionKey);
        $mock = \Mockery::mock('alias:'.CollectionHelper::class);
        $mock->shouldReceive('getCollectionFields')
            ->with($lowercaseCollectionKey)
            ->andReturn([
                'title' => 'Beratung',
                'titleList' => 'Beratungseinträge',
                'key' => $uniqueCollectionKey,
                'buttonList' => 'Neuer Eintrag',
                'description' => '',
                'hasPage' => false,
                'fields' => [
                    ['name' => 'model.title', 'label' => 'Titel', 'type' => 'translatableText', 'colspan' => 6],
                    ['name' => 'model.description', 'label' => 'Beschreibung', 'type' => 'translatableText', 'colspan' => 6],
                    ['name' => 'model.content', 'label' => 'Inhalt', 'type' => 'translatableRichText', 'colspan' => 12],
                ],
            ]);

        $collection = Collection::create([
            'tenant_id' => $this->tenantId,
            'collection_key' => $uniqueCollectionKey,
            'name' => 'Test Collection '.$uniqueSuffix,
        ]);

        // Create a page with old text format data
        $oldFormatData = [
            'title' => 'Old Format Title',
            'description' => 'Old Format Description',
            'name' => ['de' => '', 'en' => ''],
            'slug' => ['de' => '', 'en' => ''],
        ];

        $page = Page::create([
            'tenant_id' => $this->tenantId,
            'collection_id' => $collection->id,
            'data' => $oldFormatData,
            'is_active' => true,
        ]);

        // Reload the page to trigger the model boot conversion
        $page->refresh();

        expect($page->data['title'])->toBeArray()
            ->and($page->data['title']['de'])->toBe('Old Format Title')
            ->and($page->data['title']['en'])->toBe('Old Format Title')
            ->and($page->data['description'])->toBeArray()
            ->and($page->data['description']['de'])->toBe('Old Format Description')
            ->and($page->data['description']['en'])->toBe('Old Format Description');
    });

    it('handles translatableRichText and translatableTextarea types', function (): void {
        createCollectionHelperMock();

        $originalData = [
            'rich_content' => '<p>Rich text content</p>',
            'textarea_content' => 'Long textarea content',
        ];

        $convertedData = FieldTypeConverter::convertCollectionData($originalData, 'test_richtext');

        expect($convertedData['rich_content'])->toBeArray()
            ->and($convertedData['rich_content']['de'])->toBe('<p>Rich text content</p>')
            ->and($convertedData['rich_content']['en'])->toBe('<p>Rich text content</p>')
            ->and($convertedData['textarea_content'])->toBeArray()
            ->and($convertedData['textarea_content']['de'])->toBe('Long textarea content')
            ->and($convertedData['textarea_content']['en'])->toBe('Long textarea content');
    });

    it('preserves non-model fields unchanged', function (): void {
        createCollectionHelperMock();

        $originalData = [
            'title' => 'Will be converted',
            'description' => 'Will be converted',
            'name' => ['de' => 'Page Name', 'en' => 'Page Name EN'],
            'slug' => ['de' => 'page-slug', 'en' => 'page-slug-en'],
            'layout' => 'default',
            'sort' => 10,
            'custom_field' => 'Should remain unchanged',
        ];

        $convertedData = FieldTypeConverter::convertCollectionData($originalData, 'beratung');

        // Model fields should be converted
        expect($convertedData['title'])->toBeArray()
            ->and($convertedData['description'])->toBeArray()
            // Non-model fields should remain unchanged
            ->and($convertedData['name'])->toBe($originalData['name'])
            ->and($convertedData['slug'])->toBe($originalData['slug'])
            ->and($convertedData['layout'])->toBe('default')
            ->and($convertedData['sort'])->toBe(10)
            ->and($convertedData['custom_field'])->toBe('Should remain unchanged');
    });

});
