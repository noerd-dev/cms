<?php

use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Repositories\ElementAwareCollectionDefinitionRepository;
use Noerd\Cms\Repositories\YamlCollectionDefinitionRepository;
use Tests\TestCase;

uses(TestCase::class);

it('resolves YamlCollectionDefinitionRepository when mode is yaml', function (): void {
    config(['noerd.collections.mode' => 'yaml']);
    app()->forgetInstance(CollectionDefinitionRepositoryContract::class);

    $repository = app(CollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(ElementAwareCollectionDefinitionRepository::class)
        ->and($repository->inner())->toBeInstanceOf(YamlCollectionDefinitionRepository::class)
        ->and($repository->isWritable())->toBeFalse();
});

it('resolves DatabaseCollectionDefinitionRepository when mode is database', function (): void {
    config(['noerd.collections.mode' => 'database']);
    app()->forgetInstance(CollectionDefinitionRepositoryContract::class);

    $repository = app(CollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(ElementAwareCollectionDefinitionRepository::class)
        ->and($repository->inner())->toBeInstanceOf(DatabaseCollectionDefinitionRepository::class)
        ->and($repository->isWritable())->toBeTrue();
});

it('falls back to yaml mode when the config value is unknown', function (): void {
    config(['noerd.collections.mode' => 'something-invalid']);
    app()->forgetInstance(CollectionDefinitionRepositoryContract::class);

    $repository = app(CollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(ElementAwareCollectionDefinitionRepository::class)
        ->and($repository->inner())->toBeInstanceOf(YamlCollectionDefinitionRepository::class);
});
