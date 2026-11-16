<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ContentDocumentService;
use Noerd\Cms\Support\ContentLanguageSource;
use Noerd\Cms\Support\ContentTypeRegistry;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

/*
 | The languages a content type's document is written in come from ONE source,
 | declared when the type is registered: the website languages (Cms) or the
 | backend's setup languages (Setup).
 */
beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->createElementFixtures();
    SetupLanguage::ensureDefaultLanguagesForTenant($this->tenantId);
    app(ContentTypeRegistry::class)->register(key: 'zz.portal', label: 'Zz Portal', modelClass: Page::class, languageSource: ContentLanguageSource::Setup);
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('answers the codes of the source the type declares', function (): void {
    $setupCodes = SetupLanguage::activeCodes();

    expect(ContentDocumentService::editableLanguageCodes('zz.portal'))->toBe(array_values($setupCodes))
        // A website page knows the tenant's website languages only (here: en).
        ->and(ContentDocumentService::editableLanguageCodes('cms.page'))->toBe(['en'])
        // A type nobody registered is a website type.
        ->and(app(ContentTypeRegistry::class)->languageSource('zz.unknown'))->toBe(ContentLanguageSource::Cms);
});

it('never stores running text of a website page in a language the website does not run', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Home']]);

    Livewire::withUrlParams(['pageId' => $page->id])->test('cms::page-detail')
        ->set('contentDocumentDraft', [
            'language' => 'de',
            'sections' => [['kind' => 'text', 'uid' => 'nde', 'html' => '<p>Hallo</p>']],
        ])
        ->call('store');

    expect($page->elements()->where('element_key', 'rich_text')->get()->pluck('data.text.de')->filter()->all())->toBe([]);
});

it('opens a website page in the default website language when the session names another', function (): void {
    session([SetupLanguage::SESSION_KEY => 'de']);

    expect(ContentLanguageSource::Cms->selectedCode())->toBe('en');

    $this->addLanguage('de', 'Deutsch');

    expect(ContentLanguageSource::Cms->selectedCode())->toBe('de');
});

describe('default website language', function (): void {
    it('is created when the CMS app is assigned to a tenant — not for every tenant', function (): void {
        $tenant = Tenant::factory()->create();

        expect(CmsLanguage::query()->ofTenant($tenant->id)->count())->toBe(0);

        $tenant->assignApp(TenantApp::query()->where('name', 'CMS')->firstOrFail());

        expect(CmsLanguage::query()->ofTenant($tenant->id)->where('is_default', true)->value('code'))->toBe('en');
    });

    it('keeps the languages of a tenant that has some', function (): void {
        $tenant = Tenant::factory()->create();
        CmsLanguage::create(['tenant_id' => $tenant->id, 'code' => 'fr', 'name' => 'Français', 'is_active' => true, 'is_default' => true]);

        $tenant->assignApp(TenantApp::query()->where('name', 'CMS')->firstOrFail());

        expect(CmsLanguage::query()->ofTenant($tenant->id)->pluck('code')->all())->toBe(['fr']);
    });
});
