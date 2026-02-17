<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     * Seeds demo website data (Impressum page, navigation, phone parameter,
     * homepage content elements) for all tenants that already have a homepage.
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

            // 1a. Impressum page
            $impressumPage = DB::table('pages')
                ->where('tenant_id', $tenantId)
                ->whereJsonContains('slug->de', '/impressum')
                ->first();

            if (! $impressumPage) {
                $impressumPageId = DB::table('pages')->insertGetId([
                    'tenant_id' => $tenantId,
                    'name' => json_encode(['de' => 'Impressum', 'en' => 'Legal Notice']),
                    'slug' => json_encode(['de' => '/impressum', 'en' => '/legal-notice']),
                    'is_active' => true,
                    'layout' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $impressumPageId = $impressumPage->id;
            }

            // 1b. Navigation entries
            $navEntries = [
                [
                    'navigation_key' => 'main',
                    'page_id' => $homepagePageId,
                    'name' => json_encode(['de' => 'Startseite', 'en' => 'Home']),
                ],
                [
                    'navigation_key' => 'main',
                    'page_id' => $impressumPageId,
                    'name' => json_encode(['de' => 'Impressum', 'en' => 'Legal Notice']),
                ],
                [
                    'navigation_key' => 'footer',
                    'page_id' => $homepagePageId,
                    'name' => json_encode(['de' => 'Startseite', 'en' => 'Home']),
                ],
                [
                    'navigation_key' => 'footer',
                    'page_id' => $impressumPageId,
                    'name' => json_encode(['de' => 'Impressum', 'en' => 'Legal Notice']),
                ],
            ];

            foreach ($navEntries as $entry) {
                $exists = DB::table('cms_navigations')
                    ->where('tenant_id', $tenantId)
                    ->where('navigation_key', $entry['navigation_key'])
                    ->where('page_id', $entry['page_id'])
                    ->exists();

                if (! $exists) {
                    DB::table('cms_navigations')->insert([
                        'tenant_id' => $tenantId,
                        'navigation_key' => $entry['navigation_key'],
                        'page_id' => $entry['page_id'],
                        'name' => $entry['name'],
                        'new_tab' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // 1c. Global parameter: phone
            $phoneExists = DB::table('global_parameters')
                ->where('tenant_id', $tenantId)
                ->where('key', 'phone')
                ->exists();

            if (! $phoneExists) {
                DB::table('global_parameters')->insert([
                    'tenant_id' => $tenantId,
                    'key' => 'phone',
                    'value' => json_encode(['de' => '+49 123 456789', 'en' => '+49 123 456789']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 1d. Homepage content elements
            $hasElements = DB::table('element_page')
                ->where('page_id', $homepagePageId)
                ->exists();

            if (! $hasElements) {
                DB::table('element_page')->insert([
                    'page_id' => $homepagePageId,
                    'element_key' => 'text_block_2_column',
                    'sort' => 1,
                    'data' => json_encode([
                        'text1' => [
                            'de' => 'Willkommen auf unserer Website. Hier finden Sie alle wichtigen Informationen zu unserem Unternehmen und unseren Dienstleistungen.',
                            'en' => 'Welcome to our website. Here you will find all important information about our company and our services.',
                        ],
                        'text2' => [
                            'de' => 'Wir freuen uns auf Ihre Kontaktaufnahme. Nutzen Sie unser Kontaktformular oder rufen Sie uns direkt an.',
                            'en' => 'We look forward to hearing from you. Use our contact form or call us directly.',
                        ],
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('element_page')->insert([
                    'page_id' => $homepagePageId,
                    'element_key' => 'text_block_3_column',
                    'sort' => 2,
                    'data' => json_encode([
                        'text1' => [
                            'de' => "**Unsere Leistungen**\n\nWir bieten Ihnen ein breites Spektrum an professionellen Dienstleistungen, die auf Ihre individuellen Bedürfnisse zugeschnitten sind.",
                            'en' => "**Our Services**\n\nWe offer you a wide range of professional services tailored to your individual needs.",
                        ],
                        'text2' => [
                            'de' => "**Über uns**\n\nErfahren Sie mehr über unser Unternehmen, unsere Geschichte und die Menschen, die hinter unserer Arbeit stehen.",
                            'en' => "**About Us**\n\nLearn more about our company, our history, and the people behind our work.",
                        ],
                        'text3' => [
                            'de' => "**Kontakt**\n\nHaben Sie Fragen oder möchten Sie ein unverbindliches Angebot? Kontaktieren Sie uns – wir sind gerne für Sie da.",
                            'en' => "**Contact**\n\nDo you have questions or would you like a non-binding offer? Contact us – we are happy to help.",
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
        // Don't delete demo data on rollback
    }
};
