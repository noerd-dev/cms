<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    DatabaseCollectionDefinitionRepository::resetCache();

    // Use a test-only directory inside base_path so base_path() resolution works.
    $this->relativeYamlPath = 'storage/app/test-collections-' . uniqid();
    $this->absoluteYamlPath = base_path($this->relativeYamlPath);
    if (! is_dir($this->absoluteYamlPath)) {
        mkdir($this->absoluteYamlPath, 0755, true);
    }

    config(['noerd_cms.collections.yaml_path' => $this->relativeYamlPath]);
});

afterEach(function (): void {
    if (isset($this->absoluteYamlPath) && is_dir($this->absoluteYamlPath)) {
        foreach (glob($this->absoluteYamlPath . '/*.yml') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->absoluteYamlPath);
    }
});

it('imports a YAML definition into the database for a specific tenant', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    file_put_contents($this->absoluteYamlPath . '/contacts.yml', Yaml::dump([
        'title' => 'Kontakt',
        'titleList' => 'Kontakte',
        'key' => 'CONTACTS',
        'description' => '',
        'hasPage' => true,
        'fields' => [
            ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
        ],
    ]));

    $this->artisan('cms:collections:import-yaml', ['--tenant-id' => $tenant->id])
        ->assertSuccessful();

    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts')->exists())->toBeTrue();
    expect(Collection::where('tenant_id', $tenant->id)->where('collection_key', 'CONTACTS')->exists())->toBeTrue();
});

it('supports dry-run without writing', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    file_put_contents($this->absoluteYamlPath . '/contacts.yml', Yaml::dump([
        'title' => 'Kontakt',
        'titleList' => 'Kontakte',
        'key' => 'CONTACTS',
        'description' => '',
        'hasPage' => true,
        'fields' => [],
    ]));

    $this->artisan('cms:collections:import-yaml', [
        '--tenant-id' => $tenant->id,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(CollectionDefinition::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('deletes source YAML files after import with --delete flag', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    file_put_contents($this->absoluteYamlPath . '/contacts.yml', Yaml::dump([
        'title' => 'Kontakt',
        'titleList' => 'Kontakte',
        'key' => 'CONTACTS',
        'fields' => [],
    ]));

    $this->artisan('cms:collections:import-yaml', [
        '--tenant-id' => $tenant->id,
        '--delete' => true,
    ])->assertSuccessful();

    expect(file_exists($this->absoluteYamlPath . '/contacts.yml'))->toBeFalse();
    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts')->exists())->toBeTrue();
});

it('exports database definitions to YAML files', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'contacts',
        'key' => 'CONTACTS',
        'title' => 'Kontakt',
        'title_list' => 'Kontakte',
        'description' => '',
        'has_page' => true,
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    $this->artisan('cms:collections:export-yaml', ['--tenant-id' => $tenant->id])
        ->assertSuccessful();

    $exportedFile = $this->absoluteYamlPath . '/contacts.yml';
    expect(file_exists($exportedFile))->toBeTrue();

    $content = Yaml::parseFile($exportedFile);
    expect($content['key'])->toBe('CONTACTS');
    expect($content['fields'][0]['name'])->toBe('detailData.name');
});

it('refuses to overwrite existing YAML files without --force', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'contacts',
        'key' => 'CONTACTS',
        'title' => 'Kontakt New',
        'title_list' => 'Kontakte',
        'has_page' => false,
        'fields' => [],
    ]);

    file_put_contents($this->absoluteYamlPath . '/contacts.yml', Yaml::dump([
        'title' => 'OldTitle',
        'fields' => [],
    ]));

    $this->artisan('cms:collections:export-yaml', ['--tenant-id' => $tenant->id])
        ->assertSuccessful();

    $content = Yaml::parseFile($this->absoluteYamlPath . '/contacts.yml');
    expect($content['title'])->toBe('OldTitle');

    $this->artisan('cms:collections:export-yaml', [
        '--tenant-id' => $tenant->id,
        '--force' => true,
    ])->assertSuccessful();

    $content = Yaml::parseFile($this->absoluteYamlPath . '/contacts.yml');
    expect($content['title'])->toBe('Kontakt New');
});
