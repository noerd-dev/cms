<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Navigation\CollectionsNavigationProvider;
use Noerd\Cms\Navigation\PageCollectionsNavigationProvider;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
});

it('excludes element collections from the navigation providers', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);
    app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['name' => 'text', 'type' => 'translatableTextarea']],
        'Triggers Demo',
    );

    $links = collect(app(CollectionsNavigationProvider::class)->items())
        ->merge(app(PageCollectionsNavigationProvider::class)->items())
        ->pluck('link')
        ->implode(' ');

    expect(mb_strtoupper($links))->not->toContain('ELEMENT');
});
