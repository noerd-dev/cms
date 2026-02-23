<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Seed a SERVICES collection with three example entries and add
     * a collection-cards element to each tenant's homepage.
     */
    public function up(): void
    {
        $tenantsWithHomepage = DB::table('cms_settings')
            ->whereNotNull('homepage_page_id')
            ->get(['tenant_id', 'homepage_page_id']);

        $now = now();

        foreach ($tenantsWithHomepage as $setting) {
            $tenantId = $setting->tenant_id;
            $homepagePageId = $setting->homepage_page_id;

            // 1. Create SERVICES collection (skip if already exists)
            $existingCollection = DB::table('collections')
                ->where('tenant_id', $tenantId)
                ->where('collection_key', 'SERVICES')
                ->first();

            if ($existingCollection) {
                $collectionId = $existingCollection->id;
            } else {
                $collectionId = DB::table('collections')->insertGetId([
                    'tenant_id' => $tenantId,
                    'collection_key' => 'SERVICES',
                    'name' => 'Services',
                    'sort' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 2. Create example pages as collection entries
            $entries = [
                ['name' => ['de' => 'Beratung', 'en' => 'Consulting'], 'image' => ''],
                ['name' => ['de' => 'Entwicklung', 'en' => 'Development'], 'image' => ''],
                ['name' => ['de' => 'Support', 'en' => 'Support'], 'image' => ''],
            ];

            $hasEntries = DB::table('pages')
                ->where('tenant_id', $tenantId)
                ->where('collection_id', $collectionId)
                ->exists();

            if (! $hasEntries) {
                foreach ($entries as $index => $entry) {
                    DB::table('pages')->insert([
                        'tenant_id' => $tenantId,
                        'collection_id' => $collectionId,
                        'name' => json_encode($entry['name']),
                        'is_active' => true,
                        'data' => json_encode($entry),
                        'sort' => $index,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // 3. Add collection-cards element to homepage
            $hasElement = DB::table('element_page')
                ->where('page_id', $homepagePageId)
                ->where('element_key', 'collection_cards')
                ->exists();

            if (! $hasElement) {
                $maxSort = DB::table('element_page')
                    ->where('page_id', $homepagePageId)
                    ->max('sort') ?? 0;

                DB::table('element_page')->insert([
                    'page_id' => $homepagePageId,
                    'element_key' => 'collection_cards',
                    'sort' => $maxSort + 1,
                    'data' => json_encode([
                        'headline' => [
                            'de' => 'Unsere Services',
                            'en' => 'Our Services',
                        ],
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete seeded data on rollback
    }
};
