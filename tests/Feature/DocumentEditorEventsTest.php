<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

/*
 | C12: the editor's server events are window events. Each names the Livewire
 | component it is for (`owner`), and the editor ignores every other — two
 | document editors on one page never act on each other's events.
 */
beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->createElementFixtures();
    $this->page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Home']]);
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('names its component in the editor config', function (): void {
    $component = Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail');

    expect($component->instance()->contentDocumentConfig()['owner'])->toBe($component->id());
});

it('names its component in the saved event', function (): void {
    $component = Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail');

    $component->call('store')
        ->assertDispatched('content-document-saved', fn(string $event, array $params): bool => $params['owner'] === $component->id());
});

it('names its component in the load event of a language switch', function (): void {
    $component = Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail');
    $component->instance()->contentDocumentConfig();

    $component->dispatch('languageChanged')
        ->assertDispatched('content-document-load', fn(string $event, array $params): bool => $params['owner'] === $component->id()
            && $params['config']['owner'] === $component->id());
});

it('ignores the events of another editor in the browser', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/js/document/document-editor.js');

    // load() and saved() both bail out for an event of another owner.
    expect(mb_substr_count($source, 'if (!this.isForMe(detail))'))->toBe(2)
        ->and($source)->toContain('return !this.owner || detail?.owner === this.owner;');
});
