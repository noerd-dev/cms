<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class () extends Migration {
    public function up(): void
    {
        try { $this->collectionsToPathOnly(); } catch (Throwable $e) {}
        try { $this->elementPagesToPathOnly(); } catch (Throwable $e) {}
    }

    public function down(): void {}

    private function collectionsToPathOnly(): void
    {
        if (!class_exists(\Noerd\Cms\Models\Collection::class)) { return; }
        $collections = \Noerd\Cms\Models\Collection::query()->get();

        foreach ($collections as $collection) {
            $data = json_decode($collection->data ?? '[]', true) ?: [];
            $key = mb_strtolower($collection->collection_key ?? '');

            $imageFields = [];
            try {
                $fields = \Noerd\Cms\Helpers\CollectionHelper::getCollectionFields($key);
                foreach (($fields['fields'] ?? []) as $field) {
                    if (($field['type'] ?? null) === 'image' && !empty($field['name'])) {
                        $imageFields[] = str_replace('model.', '', $field['name']);
                    }
                }
            } catch (Throwable $e) {}

            if (empty($imageFields)) { continue; }

            $changed = false;
            foreach ($imageFields as $fname) {
                $val = $data[$fname] ?? null;
                $path = $this->resolvePathOnly($collection->tenant_id, $val);
                if ($path) { $data[$fname] = $path; $changed = true; }
            }
            if ($changed) { $collection->data = json_encode($data); $collection->save(); }
        }
    }

    private function elementPagesToPathOnly(): void
    {
        if (!class_exists(\Noerd\Cms\Models\ElementPage::class)) { return; }
        $pages = \Noerd\Cms\Models\ElementPage::with('page')->get();

        foreach ($pages as $elementPage) {
            $data = json_decode($elementPage->data ?? '[]', true) ?: [];
            $tenantId = optional($elementPage->page)->tenant_id;

            $imageFields = [];
            try {
                $fields = \Noerd\Cms\Helpers\FieldHelper::getElementFields($elementPage->element_key);
                foreach (($fields['fields'] ?? []) as $field) {
                    if (($field['type'] ?? null) === 'image' && !empty($field['name'])) {
                        $imageFields[] = str_replace('model.', '', $field['name']);
                    }
                }
            } catch (Throwable $e) {}

            if (empty($imageFields)) { continue; }

            $changed = false;
            foreach ($imageFields as $fname) {
                $val = $data[$fname] ?? null;
                $path = $this->resolvePathOnly($tenantId, $val);
                if ($path) { $data[$fname] = $path; $changed = true; }
            }
            if ($changed) { $elementPage->data = json_encode($data); $elementPage->save(); }
        }
    }

    private function resolvePathOnly(?int $tenantId, $value): ?string
    {
        if (is_null($value)) { return null; }

        // Already in desired format
        if (is_string($value) && str_starts_with($value, '/storage/')) {
            return $value;
        }

        // Remove legacy '@'
        if (is_string($value) && str_starts_with($value, '@')) {
            $value = ltrim($value, '@');
            if (str_starts_with($value, '/storage/')) { return $value; }
        }

        // If numeric, resolve media and convert to path-only
        if (is_numeric($value)) {
            $media = class_exists(\Noerd\Media\Models\Media::class) ? \Noerd\Media\Models\Media::find((int) $value) : null;
            if ($media) {
                try { $url = Storage::disk($media->disk)->url($media->thumbnail ?? $media->path); } catch (Throwable $e) { $url = null; }
                return $url ? $this->withoutDomain($url) : null;
            }
            return null;
        }

        // If string URL - try to normalize to /storage path
        if (is_string($value) && $value !== '') {
            // Directly contains /storage
            if (str_contains($value, '/storage/')) {
                return $this->withoutDomain($value);
            }

            // Try resolve by matching existing medias
            $mediaId = $this->tryFindMediaIdByString($tenantId, $value);
            if ($mediaId) {
                $media = \Noerd\Media\Models\Media::find($mediaId);
                if ($media) {
                    try { $url = Storage::disk($media->disk)->url($media->thumbnail ?? $media->path); } catch (Throwable $e) { $url = null; }
                    return $url ? $this->withoutDomain($url) : null;
                }
            }
        }

        return null;
    }

    private function tryFindMediaIdByString(?int $tenantId, string $value): ?int
    {
        if (!class_exists(\Noerd\Media\Models\Media::class)) { return null; }
        $query = \Noerd\Media\Models\Media::query();
        if ($tenantId) { $query->where('tenant_id', $tenantId); }
        $medias = $query->select(['id','disk','path'])->get();
        foreach ($medias as $media) {
            try { if (Storage::disk($media->disk)->url($media->path) === $value) { return (int) $media->id; } } catch (Throwable $e) {}
        }
        foreach ($medias as $media) {
            if (str_contains($value, (string) $media->path)) { return (int) $media->id; }
        }
        return null;
    }

    private function withoutDomain(string $url): ?string
    {
        if ($url === '') { return null; }
        if (str_starts_with($url, '/storage/')) { return $url; }
        $pos = strpos($url, '/storage/');
        if ($pos !== false) { return substr($url, $pos); }
        return null;
    }
};


