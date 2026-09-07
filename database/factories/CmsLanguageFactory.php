<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Models\Tenant;

class CmsLanguageFactory extends Factory
{
    protected $model = CmsLanguage::class;

    public function definition(): array
    {
        // Every tenant already owns `en` through ensureDefaultLanguageForTenant(),
        // and cms_languages is unique per (tenant_id, code) — so the codes are
        // dealt deterministically from a fixed pool that never contains `en`.
        static $sequence = 0;
        $pool = ['de', 'fr', 'es', 'it', 'nl', 'da', 'sv', 'pl', 'pt', 'cs', 'fi', 'hu', 'no', 'ro', 'tr'];
        $code = $pool[$sequence++ % count($pool)];

        return [
            'tenant_id' => Tenant::factory(),
            'code' => $code,
            'name' => mb_strtoupper($code),
        ];
    }

    public function english(): static
    {
        return $this->state(fn() => [
            'code' => 'en',
            'name' => 'English',
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
