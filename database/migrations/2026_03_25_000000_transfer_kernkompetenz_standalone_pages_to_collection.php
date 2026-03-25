<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $collectionPages = DB::table('pages')
                ->where('collection_id', 4)
                ->whereNotNull('data')
                ->get();

            foreach ($collectionPages as $collectionPage) {
                $data = json_decode($collectionPage->data, true);

                $standalonePageId = $data['href_page_id'] ?? null;
                if (! $standalonePageId) {
                    continue;
                }

                $standalonePage = DB::table('pages')->where('id', $standalonePageId)->first();
                if (! $standalonePage) {
                    continue;
                }

                DB::table('pages')->where('id', $collectionPage->id)->update([
                    'name' => $standalonePage->name,
                    'slug' => $standalonePage->slug,
                    'layout' => $standalonePage->layout,
                    'is_active' => $standalonePage->is_active,
                    'meta_title' => $standalonePage->meta_title,
                    'meta_description' => $standalonePage->meta_description,
                    'meta_noindex' => $standalonePage->meta_noindex,
                    'updated_at' => now(),
                ]);

                DB::table('element_page')
                    ->where('page_id', $standalonePageId)
                    ->update(['page_id' => $collectionPage->id]);

                unset($data['href_page_id']);
                DB::table('pages')->where('id', $collectionPage->id)->update([
                    'data' => json_encode($data),
                ]);

                DB::table('pages')->where('id', $standalonePageId)->delete();
            }
        });
    }

    public function down(): void
    {
        $mapping = [
            14 => 42,
            15 => 53,
            16 => 54,
            17 => 55,
            18 => 56,
            19 => 57,
            173 => 172,
        ];

        DB::transaction(function () use ($mapping) {
            $now = now();

            foreach ($mapping as $collectionPageId => $standalonePageId) {
                $collectionPage = DB::table('pages')->where('id', $collectionPageId)->first();
                if (! $collectionPage) {
                    continue;
                }

                DB::table('pages')->insert([
                    'id' => $standalonePageId,
                    'tenant_id' => $collectionPage->tenant_id,
                    'collection_id' => null,
                    'name' => $collectionPage->name,
                    'slug' => $collectionPage->slug,
                    'layout' => $collectionPage->layout,
                    'is_active' => $collectionPage->is_active,
                    'meta_title' => $collectionPage->meta_title,
                    'meta_description' => $collectionPage->meta_description,
                    'meta_noindex' => $collectionPage->meta_noindex,
                    'data' => null,
                    'sort' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('element_page')
                    ->where('page_id', $collectionPageId)
                    ->update(['page_id' => $standalonePageId]);

                $data = json_decode($collectionPage->data, true) ?? [];
                $data['href_page_id'] = $standalonePageId;

                DB::table('pages')->where('id', $collectionPageId)->update([
                    'name' => null,
                    'slug' => null,
                    'layout' => null,
                    'meta_title' => null,
                    'meta_description' => null,
                    'data' => json_encode($data),
                    'updated_at' => $now,
                ]);
            }
        });
    }
};
