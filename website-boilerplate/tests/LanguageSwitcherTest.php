<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Noerd\Website\Models\Language;
use Noerd\Website\Models\Tenant;
use Tests\TestCase;

class LanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant for testing
        $this->tenant = Tenant::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_loads_languages_from_cms_frontend_model(): void
    {
        // Create test languages
        Language::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'de',
            'name' => 'Deutsch',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        Language::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'en',
            'name' => 'English',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);

        $component = Volt::test('frontend-language-switcher', ['tenantId' => $this->tenant->id]);

        $this->assertCount(2, $component->get('languages'));
        $this->assertEquals('de', $component->get('languages')[0]['code']);
        $this->assertEquals('en', $component->get('languages')[1]['code']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hides_when_only_one_language_is_active(): void
    {
        // Create only one active language
        Language::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'de',
            'name' => 'Deutsch',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $component = Volt::test('frontend-language-switcher', ['tenantId' => $this->tenant->id]);

        $html = $component->html();

        // Should not show language switcher when only one language
        $this->assertCount(1, $component->get('languages'));
        $this->assertStringNotContainsString('cursor-pointer', $html);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_switch_languages(): void
    {
        Language::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'de',
            'name' => 'Deutsch',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        Language::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'en',
            'name' => 'English',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);

        $component = Volt::test('frontend-language-switcher', ['tenantId' => $this->tenant->id]);

        // Switch to English
        $component->call('setLanguage', 'en');

        $this->assertEquals('en', session('selectedLanguage'));
    }
}
