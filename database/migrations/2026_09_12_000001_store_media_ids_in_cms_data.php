<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Image fields used to store the resolved URL of a media file. The media disk
 * now mirrors the library's folder tree, so a file's path — and with it that
 * URL — changes as soon as the file is moved. Store the media id instead; the
 * URL is resolved when a page is rendered.
 *
 * This migration must run BEFORE `media:restructure`: it maps the stored URLs
 * back to media rows through their current (still flat) path.
 *
 * A value that resolves to no media row is left alone — it may be an external
 * URL, or an upload made without the media library.
 */
return new class extends Migration {
    /** @var array<string, int>|null URL as stored => media id */
    private ?array $urlMap = null;

    public function up(): void
    {
        if (! Schema::hasTable('medias')) {
            return;
        }

        foreach (['cms_pages', 'cms_page_elements'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'data')) {
                $this->rewriteTable($table);
            }
        }
    }

    public function down(): void
    {
        // Not reversible: the original URLs are derivable from the ids, but a
        // rollback would have to guess which values were ids to begin with.
    }

    private function rewriteTable(string $table): void
    {
        DB::table($table)
            ->select('id', 'data')
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $data = is_string($row->data) ? json_decode($row->data, true) : $row->data;

                    if (! is_array($data)) {
                        continue;
                    }

                    $rewritten = $this->rewrite($data);

                    if ($rewritten === $data) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        'data' => json_encode($rewritten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function rewrite(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->rewrite($value);

                continue;
            }

            if (is_string($value) && isset($this->urlMap()[$value])) {
                $data[$key] = $this->urlMap()[$value];
            }
        }

        return $data;
    }

    /**
     * Every media file indexed by the URL shape the CMS used to store
     * (`mb_strstr(Storage::url($path), '/storage')`).
     *
     * @return array<string, int>
     */
    private function urlMap(): array
    {
        if ($this->urlMap !== null) {
            return $this->urlMap;
        }

        $this->urlMap = [];

        DB::table('medias')
            ->select('id', 'path', 'disk')
            ->orderBy('id')
            ->each(function (object $media): void {
                if (blank($media->path)) {
                    return;
                }

                try {
                    $url = Storage::disk((string) $media->disk)->url((string) $media->path);
                } catch (Throwable) {
                    return;
                }

                $relative = mb_strstr($url, '/storage');

                if ($relative !== false) {
                    $this->urlMap[$relative] = (int) $media->id;
                }
            });

        return $this->urlMap;
    }
};
