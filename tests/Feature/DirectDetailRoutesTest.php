<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

it('has direct route for page-detail', function (): void {
    expect(Route::has('cms.page.detail'))->toBeTrue();
});

it('has direct route for navigation-detail', function (): void {
    expect(Route::has('cms.navigation.detail'))->toBeTrue();
});

it('has direct route for global-parameter-detail', function (): void {
    expect(Route::has('cms.global-parameter.detail'))->toBeTrue();
});

it('has direct route for collection-detail', function (): void {
    expect(Route::has('cms.collection.detail'))->toBeTrue();
});

it('has direct route for form-request-detail', function (): void {
    expect(Route::has('cms.form-request.detail'))->toBeTrue();
});

it('has direct route for form-type-detail', function (): void {
    expect(Route::has('cms.form-type.detail'))->toBeTrue();
});

it('has direct route for cms-language-detail', function (): void {
    expect(Route::has('cms.language.detail'))->toBeTrue();
});
