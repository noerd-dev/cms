<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsLanguage;

uses(Tests\TestCase::class, RefreshDatabase::class, Noerd\Cms\Tests\Traits\CreatesCmsUser::class);

/*
 | Slug generation for non-default languages: the default language stays
 | unprefixed, every other active language gets its code as a path prefix,
 | and umlauts transliterate. Complements PageSlugUniquenessTest, which runs
 | single-language.
 */

it('prefixes non-default language slugs with the language code', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $defaultCode = CmsLanguage::where('tenant_id', $tenant->id)->where('is_default', true)->value('code');
    $otherCode = $defaultCode === 'en' ? 'de' : 'en';

    CmsLanguage::firstOrCreate(
        ['tenant_id' => $tenant->id, 'code' => $otherCode],
        ['name' => 'Other', 'is_active' => true, 'is_default' => false],
    );

    $component = Livewire::test('cms::page-detail');
    $instance = $component->instance();

    expect($instance->generateSlug('Über uns', $defaultCode))->toBe('/ueber-uns')
        ->and($instance->generateSlug('Über uns', $otherCode))->toBe('/' . $otherCode . '/ueber-uns');
});

it('keeps the default language unprefixed whatever it is', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $default = CmsLanguage::where('tenant_id', $tenant->id)->where('is_default', true)->first();

    $component = Livewire::test('cms::page-detail');

    expect($component->instance()->generateSlug('Startseite', $default->code))->toBe('/startseite');
});
