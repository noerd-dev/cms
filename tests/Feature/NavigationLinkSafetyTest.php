<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
});

it('refuses a navigation link that is no safe URL', function (string $link): void {
    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['en' => 'Evil'])
        ->set('detailData.link', $link)
        ->call('store')
        ->assertHasErrors('detailData.link');

    expect(Navigation::query()->count())->toBe(0);
})->with([
    'javascript' => ['javascript:alert(document.cookie)'],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'protocol relative' => ['//evil.com'],
    'longer than the column' => ['https://example.com/' . str_repeat('a', 300)],
]);

it('stores web, mail and site links', function (string $link): void {
    Livewire::test('cms::navigation-detail')
        ->set('detailData.navigation_key', 'MAIN')
        ->set('detailData.name', ['en' => 'Ok'])
        ->set('detailData.link', $link)
        ->call('store')
        ->assertHasNoErrors();

    expect(Navigation::query()->value('link'))->toBe($link);
})->with([
    ['https://example.com/contact'],
    ['mailto:info@example.com'],
    ['/kontakt'],
]);
