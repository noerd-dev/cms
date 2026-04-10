<?php

use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Support\CollectionDefinitionData;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Helpers\TenantHelper;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    DatabaseCollectionDefinitionRepository::resetCache();
});

it('returns an empty collection when the tenant has no definitions', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    $repo = new DatabaseCollectionDefinitionRepository();

    expect($repo->all($tenant->id))->toHaveCount(0);
});

it('persists a new definition via save with current user as creator', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);
    TenantHelper::setSelectedTenantId($tenant->id);

    $repo = new DatabaseCollectionDefinitionRepository();
    $data = new CollectionDefinitionData(
        filename: 'contacts',
        key: 'CONTACTS',
        title: 'Kontakt',
        titleList: 'Kontakte',
        description: null,
        hasPage: true,
        fields: [['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6]],
    );

    $filename = $repo->save($data);

    expect($filename)->toBe('contacts');
    $model = CollectionDefinition::where('tenant_id', $tenant->id)->first();
    expect($model->key)->toBe('CONTACTS');
    expect($model->created_by)->toBe($user->id);
});

it('updates an existing definition identified by originalFilename', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    TenantHelper::setSelectedTenantId($tenant->id);

    $repo = new DatabaseCollectionDefinitionRepository();

    $repo->save(new CollectionDefinitionData('contacts', 'CONTACTS', 'Old', 'Olds', null, false, []));

    $updated = new CollectionDefinitionData('contacts', 'CONTACTS', 'New', 'News', 'updated', true, []);
    $repo->save($updated, originalFilename: 'contacts');

    $model = CollectionDefinition::where('tenant_id', $tenant->id)->first();
    expect($model->title)->toBe('New');
    expect($model->description)->toBe('updated');
    expect($model->has_page)->toBeTrue();
    expect(CollectionDefinition::where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('renames filename when saving with a new filename', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    TenantHelper::setSelectedTenantId($tenant->id);

    $repo = new DatabaseCollectionDefinitionRepository();
    $repo->save(new CollectionDefinitionData('contacts', 'CONTACTS', 'Kontakt', 'Kontakte', null, false, []));

    $repo->save(
        new CollectionDefinitionData('clients', 'CLIENTS', 'Kunden', 'Kunden', null, false, []),
        originalFilename: 'contacts',
    );

    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'clients')->exists())->toBeTrue();
    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts')->exists())->toBeFalse();
});

it('copies a definition with filename, key, title and title_list all suffixed with 2', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    TenantHelper::setSelectedTenantId($tenant->id);

    $repo = new DatabaseCollectionDefinitionRepository();
    $repo->save(new CollectionDefinitionData('contacts', 'CONTACTS', 'Kontakt', 'Kontakte', null, false, [
        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
    ]));

    $newFilename = $repo->copy('contacts');

    expect($newFilename)->toBe('contacts2');
    $copy = CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts2')->first();
    expect($copy->key)->toBe('CONTACTS2');
    expect($copy->title)->toBe('Kontakt2');
    expect($copy->title_list)->toBe('Kontakte2');
    expect($copy->fields)->toHaveCount(1);
});

it('throws when copy target already exists', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    TenantHelper::setSelectedTenantId($tenant->id);

    $repo = new DatabaseCollectionDefinitionRepository();
    $repo->save(new CollectionDefinitionData('contacts', 'CONTACTS', 'Kontakt', 'Kontakte', null, false, []));
    $repo->save(new CollectionDefinitionData('contacts2', 'CONTACTS2', 'Kontakt 2', 'Kontakte 2', null, false, []));

    expect(fn () => $repo->copy('contacts'))->toThrow(\RuntimeException::class);
});

it('deletes a definition and invalidates cache', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    TenantHelper::setSelectedTenantId($tenant->id);

    $repo = new DatabaseCollectionDefinitionRepository();
    $repo->save(new CollectionDefinitionData('contacts', 'CONTACTS', 'Kontakt', 'Kontakte', null, false, []));

    expect($repo->resolveFields('contacts'))->not->toBeNull();

    $repo->delete('contacts');

    expect($repo->resolveFields('contacts'))->toBeNull();
    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts')->exists())->toBeFalse();
});

it('returns the YAML-shaped payload from resolveFields', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    TenantHelper::setSelectedTenantId($tenant->id);

    $repo = new DatabaseCollectionDefinitionRepository();
    $repo->save(new CollectionDefinitionData(
        filename: 'contacts',
        key: 'CONTACTS',
        title: 'Kontakt',
        titleList: 'Kontakte',
        description: null,
        hasPage: true,
        fields: [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
            ['name' => 'collection.page_id', 'label' => 'Page', 'type' => 'text', 'colspan' => 6],
        ],
    ));

    $fields = $repo->resolveFields('contacts');

    expect($fields['title'])->toBe('Kontakt');
    expect($fields['titleList'])->toBe('Kontakte');
    expect($fields['hasPage'])->toBeTrue();
    expect($fields['fields'])->toHaveCount(1);
    expect($fields['fields'][0]['name'])->toBe('detailData.name');
});

it('preserves extra field properties (modalComponent, relationField) through save and resolveFields', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    TenantHelper::setSelectedTenantId($tenant->id);

    $repo = new DatabaseCollectionDefinitionRepository();
    $repo->save(new CollectionDefinitionData(
        filename: 'beratung',
        key: 'BERATUNG',
        title: 'Beratung',
        titleList: 'Beratungspunkte',
        description: null,
        hasPage: false,
        fields: [
            [
                'name' => 'page_id',
                'label' => 'Verlinkte Seite',
                'type' => 'relation',
                'colspan' => 6,
                'modalComponent' => 'pages-list',
                'relationField' => 'relationTitles.page_id',
            ],
        ],
    ));

    $fields = $repo->resolveFields('beratung');

    expect($fields['fields'])->toHaveCount(1);
    expect($fields['fields'][0]['name'])->toBe('detailData.page_id');
    expect($fields['fields'][0]['modalComponent'])->toBe('pages-list');
    expect($fields['fields'][0]['relationField'])->toBe('relationTitles.page_id');

    $definition = $repo->find('beratung');
    expect($definition->fields[0]['modalComponent'])->toBe('pages-list');
    expect($definition->fields[0]['relationField'])->toBe('relationTitles.page_id');
});

it('scopes queries by tenant_id', function (): void {
    ['tenant' => $tenantA] = $this->createUserWithCmsAccess();
    ['tenant' => $tenantB] = $this->createUserWithCmsAccess();

    $repo = new DatabaseCollectionDefinitionRepository();
    $repo->save(
        new CollectionDefinitionData('contacts', 'CONTACTS', 'Kontakt', 'Kontakte', null, false, []),
        tenantId: $tenantA->id,
    );

    expect($repo->all($tenantA->id))->toHaveCount(1);
    expect($repo->all($tenantB->id))->toHaveCount(0);
});

it('reports isWritable as true', function (): void {
    $repo = new DatabaseCollectionDefinitionRepository();
    expect($repo->isWritable())->toBeTrue();
});

it('throws when saving without a tenant context', function (): void {
    $repo = new DatabaseCollectionDefinitionRepository();
    TenantHelper::clear();
    $data = new CollectionDefinitionData('contacts', 'CONTACTS', 'Kontakt', 'Kontakte', null, false, []);

    expect(fn () => $repo->save($data))->toThrow(\RuntimeException::class);
});
