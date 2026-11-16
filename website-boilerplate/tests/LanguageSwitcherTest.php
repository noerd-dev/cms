<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Website\Models\Language;
use Noerd\Website\Models\Tenant;
use Noerd\Website\Support\WebsiteLanguage;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    $this->german = Language::factory()->default()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'de',
        'name' => 'Deutsch',
    ]);
});

it('loads languages from cms frontend model', function (): void {
    Language::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'en',
        'name' => 'English',
    ]);

    $component = Livewire::test('website::frontend-language-switcher', ['tenantId' => $this->tenant->id]);

    $this->assertCount(2, $component->get('languages'));
    $this->assertEquals('de', $component->get('languages')[0]['code']);
    $this->assertEquals('en', $component->get('languages')[1]['code']);
});

it('hides when only one language is active', function (): void {
    // Only the German language from the beforeEach is active.
    $component = Livewire::test('website::frontend-language-switcher', ['tenantId' => $this->tenant->id]);

    // Should not render any language option links when only one language is active
    $this->assertCount(1, $component->get('languages'));
    $component->assertDontSeeHtml('setLanguage');
});

it('can switch languages', function (): void {
    Language::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'en',
        'name' => 'English',
    ]);

    $component = Livewire::test('website::frontend-language-switcher', ['tenantId' => $this->tenant->id]);

    // Switch to English
    $component->call('setLanguage', 'en');

    $this->assertEquals('en', session(WebsiteLanguage::SESSION_KEY));
});

it('never changes the backend language of the session', function (): void {
    Language::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'en', 'name' => 'English']);

    // The signed-in user's backend language lives under its own key.
    session(['selectedLanguage' => 'de']);

    Livewire::test('website::frontend-language-switcher', ['tenantId' => $this->tenant->id])
        ->call('setLanguage', 'en');

    expect(session(WebsiteLanguage::SESSION_KEY))->toBe('en')
        ->and(session('selectedLanguage'))->toBe('de');
});
