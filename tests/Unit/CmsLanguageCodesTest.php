<?php

declare(strict_types=1);

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

/**
 * A tenant adds a content language through the CMS UI — no code change anywhere.
 * These tests pin that contract: the resolved codes follow the database.
 */
beforeEach(function (): void {
    ['tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    CmsLanguageCodes::clearCache();
});

it('returns the active codes of the current tenant, default language first', function (): void {
    CmsLanguage::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'da',
        'name' => 'Dansk',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    expect(CmsLanguageCodes::active())->toBe(['da', 'en']);
});

it('skips inactive languages', function (): void {
    CmsLanguage::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'da',
        'name' => 'Dansk',
        'is_active' => false,
        'is_default' => false,
        'sort_order' => 1,
    ]);

    expect(CmsLanguageCodes::active())->not->toContain('da');
});

it('recognises a tenant-added code as translatable on top of the built-in baseline', function (): void {
    CmsLanguage::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'da',
        'name' => 'Dansk',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 1,
    ]);
    CmsLanguageCodes::clearCache();

    expect(CmsLanguageCodes::known())
        ->toContain('da')
        ->and(CmsLanguageCodes::known())->toContain('de')
        ->and(CmsLanguageCodes::known())->toContain('en');
});

it('keeps recognising a code after its language was deleted', function (): void {
    $language = CmsLanguage::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'fr',
        'name' => 'Francais',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 1,
    ]);
    $language->delete();

    expect(CmsLanguageCodes::known())->toContain('fr');
});

it('drops the memoized codes when a language is saved', function (): void {
    expect(CmsLanguageCodes::active())->not->toContain('da');

    CmsLanguage::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'da',
        'name' => 'Dansk',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 1,
    ]);

    expect(CmsLanguageCodes::active())->toContain('da');
});
