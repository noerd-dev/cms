<?php

use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Repositories\YamlCollectionDefinitionRepository;

uses(Tests\TestCase::class);

it('resolves YamlCollectionDefinitionRepository when mode is yaml', function (): void {
    config(['noerd_cms.collections.mode' => 'yaml']);
    app()->forgetInstance(CollectionDefinitionRepositoryContract::class);

    $repository = app(CollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(YamlCollectionDefinitionRepository::class);
    expect($repository->isWritable())->toBeFalse();
});

it('resolves DatabaseCollectionDefinitionRepository when mode is database', function (): void {
    config(['noerd_cms.collections.mode' => 'database']);
    app()->forgetInstance(CollectionDefinitionRepositoryContract::class);

    $repository = app(CollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(DatabaseCollectionDefinitionRepository::class);
    expect($repository->isWritable())->toBeTrue();
});

it('falls back to yaml mode when the config value is unknown', function (): void {
    config(['noerd_cms.collections.mode' => 'something-invalid']);
    app()->forgetInstance(CollectionDefinitionRepositoryContract::class);

    $repository = app(CollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(YamlCollectionDefinitionRepository::class);
});
