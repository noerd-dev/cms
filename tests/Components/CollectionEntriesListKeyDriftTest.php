<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Repositories\ElementAwareCollectionDefinitionRepository;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    // The repository singleton is resolved at boot from NOERD_COLLECTIONS_MODE
    // (yaml in testing); swap in the database-backed repository explicitly.
    app()->instance(
        CollectionDefinitionRepositoryContract::class,
        new ElementAwareCollectionDefinitionRepository(
            new DatabaseCollectionDefinitionRepository,
            app(ElementCollectionService::class),
        ),
    );
});

it('resolves the existing collection row via the definition key instead of creating a hyphenated duplicate', function (): void {
    $real = Collection::create([
        'tenant_id' => $this->tenant->id,
        'collection_key' => 'STELLENANGEBOTE_PERMANENT',
        'name' => 'Stellenangebote Permanent',
        'is_element_collection' => false,
    ]);

    CollectionDefinition::create([
        'tenant_id' => $this->tenant->id,
        'filename' => 'stellenangebote-permanent',
        'key' => 'STELLENANGEBOTE_PERMANENT',
        'title' => 'Stellenangebote Permanent',
        'title_list' => 'Stellenangebote Permanent',
        'has_page' => true,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Name', 'colspan' => 6],
        ],
    ]);

    $this->get(route('cms.collections').'?key=stellenangebote-permanent')->assertOk();

    expect(Collection::query()->where('collection_key', 'STELLENANGEBOTE-PERMANENT')->exists())->toBeFalse()
        ->and(Collection::query()->where('collection_key', 'STELLENANGEBOTE_PERMANENT')->count())->toBe(1)
        ->and(Collection::find($real->id))->not->toBeNull();
});

it('still creates a collection row from the uppercased key when no definition exists', function (): void {
    $this->get(route('cms.collections').'?key=adhoc-things')->assertOk();

    expect(Collection::query()->where('collection_key', 'ADHOC-THINGS')->count())->toBe(1);
});
