<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!class_exists(\Noerd\Media\Models\Media::class)) { return; }

        // 1) Strip stray leading '@' from thumbnail and path values
        DB::table('medias')->where('thumbnail', 'like', '@%')->update([
            'thumbnail' => DB::raw("SUBSTR(thumbnail, 2)")
        ]);
        DB::table('medias')->where('path', 'like', '@%')->update([
            'path' => DB::raw("SUBSTR(path, 2)")
        ]);

        // 2) Ensure thumbnails have an extension; default to .jpg if missing
        $rows = DB::table('medias')->select(['id','thumbnail'])->whereNotNull('thumbnail')->get();
        foreach ($rows as $row) {
            $thumb = (string) $row->thumbnail;
            // has path segment but no extension
            if ($thumb !== '' && !preg_match('/\.[a-zA-Z0-9]{2,5}$/', $thumb)) {
                DB::table('medias')->where('id', $row->id)->update([
                    'thumbnail' => $thumb . '.jpg'
                ]);
            }
        }
    }

    public function down(): void
    {
        // no-op
    }
};


