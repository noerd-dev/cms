<?php

use Noerd\Cms\Repositories\YamlCollectionDefinitionRepository;
use Noerd\Cms\Support\CollectionDefinitionData;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);

function yamlRepoTmpDir(): string
{
    $dir = sys_get_temp_dir() . '/cms-yaml-repo-test-' . uniqid();
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function writeYamlDefinition(string $dir, string $filename, array $data): void
{
    file_put_contents($dir . '/' . $filename . '.yml', Yaml::dump($data, 4, 2));
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir() . '/cms-yaml-repo-test-*/*.yml') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob(sys_get_temp_dir() . '/cms-yaml-repo-test-*') ?: [] as $dir) {
        @rmdir($dir);
    }
});

it('returns empty collection when the directory does not exist', function (): void {
    $repo = new YamlCollectionDefinitionRepository('/nonexistent/path/does/not/exist');

    expect($repo->all())->toHaveCount(0);
});

it('parses YAML files and strips the detailData prefix from fields', function (): void {
    $dir = yamlRepoTmpDir();
    writeYamlDefinition($dir, 'contacts', [
        'title' => 'Kontakt',
        'titleList' => 'Kontakte',
        'key' => 'CONTACTS',
        'description' => '',
        'hasPage' => true,
        'fields' => [
            ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    $repo = new YamlCollectionDefinitionRepository($dir);
    $definition = $repo->find('contacts');

    expect($definition)->toBeInstanceOf(CollectionDefinitionData::class);
    expect($definition->key)->toBe('CONTACTS');
    expect($definition->title)->toBe('Kontakt');
    expect($definition->hasPage)->toBeTrue();
    expect($definition->fields)->toHaveCount(1);
    expect($definition->fields[0]['name'])->toBe('name');
});

it('preserves extra field properties like modalComponent and relationField when loading YAML', function (): void {
    $dir = yamlRepoTmpDir();
    writeYamlDefinition($dir, 'beratung', [
        'title' => 'Beratung',
        'titleList' => 'Beratungspunkte',
        'key' => 'BERATUNG',
        'hasPage' => false,
        'fields' => [
            [
                'name' => 'detailData.page_id',
                'label' => 'Verlinkte Seite',
                'type' => 'relation',
                'colspan' => 6,
                'modalComponent' => 'pages-list',
                'relationField' => 'relationTitles.page_id',
            ],
        ],
    ]);

    $repo = new YamlCollectionDefinitionRepository($dir);
    $definition = $repo->find('beratung');

    expect($definition->fields)->toHaveCount(1);
    expect($definition->fields[0]['name'])->toBe('page_id');
    expect($definition->fields[0]['modalComponent'])->toBe('pages-list');
    expect($definition->fields[0]['relationField'])->toBe('relationTitles.page_id');
});

it('sorts results by titleList case-insensitively', function (): void {
    $dir = yamlRepoTmpDir();
    writeYamlDefinition($dir, 'zebra', ['title' => 'Zebra', 'titleList' => 'zebra', 'key' => 'ZEBRA', 'fields' => []]);
    writeYamlDefinition($dir, 'alpha', ['title' => 'Alpha', 'titleList' => 'Alpha', 'key' => 'ALPHA', 'fields' => []]);
    writeYamlDefinition($dir, 'mango', ['title' => 'Mango', 'titleList' => 'mango', 'key' => 'MANGO', 'fields' => []]);

    $repo = new YamlCollectionDefinitionRepository($dir);
    $all = $repo->all();

    expect($all->pluck('key')->toArray())->toBe(['ALPHA', 'MANGO', 'ZEBRA']);
});

it('finds a definition by key', function (): void {
    $dir = yamlRepoTmpDir();
    writeYamlDefinition($dir, 'contacts', ['title' => 'Kontakt', 'titleList' => 'Kontakte', 'key' => 'CONTACTS', 'fields' => []]);

    $repo = new YamlCollectionDefinitionRepository($dir);

    expect($repo->findByKey('CONTACTS'))->not->toBeNull();
    expect($repo->findByKey('contacts'))->not->toBeNull();
    expect($repo->findByKey('MISSING'))->toBeNull();
});

it('filters out collection.page_id in resolveFields', function (): void {
    $dir = yamlRepoTmpDir();
    writeYamlDefinition($dir, 'contacts', [
        'title' => 'Kontakt',
        'titleList' => 'Kontakte',
        'key' => 'CONTACTS',
        'fields' => [
            ['name' => 'collection.page_id', 'label' => 'Page', 'type' => 'text'],
            ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text'],
        ],
    ]);

    $repo = new YamlCollectionDefinitionRepository($dir);
    $fields = $repo->resolveFields('contacts');

    expect($fields['fields'])->toHaveCount(1);
    expect($fields['fields'][0]['name'])->toBe('detailData.name');
});

it('reports isWritable as false', function (): void {
    $repo = new YamlCollectionDefinitionRepository(yamlRepoTmpDir());
    expect($repo->isWritable())->toBeFalse();
});

it('throws RuntimeException when save is called', function (): void {
    $repo = new YamlCollectionDefinitionRepository(yamlRepoTmpDir());
    $data = new CollectionDefinitionData('test', 'TEST', 'Test', 'Tests', null, false, []);

    expect(fn() => $repo->save($data))->toThrow(\RuntimeException::class);
});

it('throws RuntimeException when copy is called', function (): void {
    $repo = new YamlCollectionDefinitionRepository(yamlRepoTmpDir());

    expect(fn() => $repo->copy('whatever'))->toThrow(\RuntimeException::class);
});

it('throws RuntimeException when delete is called', function (): void {
    $repo = new YamlCollectionDefinitionRepository(yamlRepoTmpDir());

    expect(fn() => $repo->delete('whatever'))->toThrow(\RuntimeException::class);
});
