<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        try { $this->processCollections(); } catch (Throwable $e) {}
        try { $this->processElementPages(); } catch (Throwable $e) {}
    }

    private function processCollections(): void
    {
        if (!class_exists(\Noerd\Cms\Models\Collection::class)) { return; }
        $collections = \Noerd\Cms\Models\Collection::query()->get();

        foreach ($collections as $collection) {
            $data = json_decode($collection->data ?? '[]', true) ?: [];
            $key = strtolower($collection->collection_key ?? '');

            // Collect image fields
            $imageFields = [];
            try {
                $fieldsConfig = \Noerd\Cms\Helpers\CollectionHelper::getCollectionFields($key);
                foreach (($fieldsConfig['fields'] ?? []) as $field) {
                    if (($field['type'] ?? null) === 'image' && !empty($field['name'])) {
                        $imageFields[] = str_replace('model.', '', $field['name']);
                    }
                }
            } catch (Throwable $e) {
                // no config, skip
            }

            if (empty($imageFields)) { continue; }

            $changed = false;
            foreach ($imageFields as $fieldName) {
                $val = $data[$fieldName] ?? null;
                $mediaId = $this->ensureMediaForValue($collection->tenant_id, $val);
                if ($mediaId) {
                    $data[$fieldName] = $mediaId;
                    $changed = true;
                }
            }

            if ($changed) {
                $collection->data = json_encode($data);
                $collection->save();
            }
        }
    }

    private function processElementPages(): void
    {
        if (!class_exists(\Noerd\Cms\Models\ElementPage::class)) { return; }
        $elementPages = \Noerd\Cms\Models\ElementPage::query()->with('page')->get();

        foreach ($elementPages as $elementPage) {
            $data = json_decode($elementPage->data ?? '[]', true) ?: [];
            $tenantId = optional($elementPage->page)->tenant_id;

            // Collect image fields
            $imageFields = [];
            try {
                $fieldsConfig = \Noerd\Cms\Helpers\FieldHelper::getElementFields($elementPage->element_key);
                foreach (($fieldsConfig['fields'] ?? []) as $field) {
                    if (($field['type'] ?? null) === 'image' && !empty($field['name'])) {
                        $imageFields[] = str_replace('model.', '', $field['name']);
                    }
                }
            } catch (Throwable $e) {
                // no config, skip
            }

            if (empty($imageFields)) { continue; }

            $changed = false;
            foreach ($imageFields as $fieldName) {
                $val = $data[$fieldName] ?? null;
                $mediaId = $this->ensureMediaForValue($tenantId, $val);
                if ($mediaId) {
                    $data[$fieldName] = $mediaId;
                    $changed = true;
                }
            }

            if ($changed) {
                $elementPage->data = json_encode($data);
                $elementPage->save();
            }
        }
    }

    private function ensureMediaForValue(?int $tenantId, $value): ?int
    {
        if (is_numeric($value)) {
            // already an id
            return (int) $value;
        }
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (!class_exists(\Nywerk\Media\Models\Media::class)) {
            return null;
        }

        // Try resolve to existing media
        $mediaId = $this->resolveExistingMediaId($tenantId, $value);
        if ($mediaId) { return $mediaId; }

        // Try to import file to images disk and create media
        $importedId = $this->importFileAsMedia($tenantId, $value);
        return $importedId;
    }

    private function resolveExistingMediaId(?int $tenantId, string $value): ?int
    {
        $query = \Nywerk\Media\Models\Media::query();
        if ($tenantId) { $query->where('tenant_id', $tenantId); }
        $medias = $query->select(['id','disk','path'])->get();
        foreach ($medias as $media) {
            try {
                $url = Storage::disk($media->disk)->url($media->path);
                if ($url === $value) { return (int) $media->id; }
            } catch (Throwable $e) {}
        }
        foreach ($medias as $media) {
            if (str_contains($value, (string) $media->path)) { return (int) $media->id; }
        }
        return null;
    }

    private function importFileAsMedia(?int $tenantId, string $value): ?int
    {
        // Detect disk and relative path from URL-like value
        $candidates = [];
        try { $candidates['images'] = $this->relativePathFromUrl('images', $value); } catch (Throwable $e) {}
        try { $candidates['public'] = $this->relativePathFromUrl('public', $value); } catch (Throwable $e) {}

        // If file exists on images disk already, just register media if missing
        if (!empty($candidates['images']) && Storage::disk('images')->exists($candidates['images'])) {
            return $this->createMediaRecord($tenantId, 'images', $candidates['images']);
        }

        // If exists on public disk, copy into images disk under tenant folder
        if (!empty($candidates['public']) && Storage::disk('public')->exists($candidates['public'])) {
            $originalName = basename($candidates['public']);
            $randomName = Str::random() . '_' . $originalName;
            $destinationPath = ($tenantId ?: 0) . '/' . $randomName;

            $stream = Storage::disk('public')->readStream($candidates['public']);
            if ($stream) {
                Storage::disk('images')->put($destinationPath, $stream);
                if (is_resource($stream)) { fclose($stream); }
                return $this->createMediaRecord($tenantId, 'images', $destinationPath);
            }
        }

        // As a last resort, try to read by stripping leading '/storage/' to public disk
        if (str_starts_with($value, '/storage/')) {
            $rel = substr($value, strlen('/storage/'));
            if (Storage::disk('public')->exists($rel)) {
                $originalName = basename($rel);
                $randomName = Str::random() . '_' . $originalName;
                $destinationPath = ($tenantId ?: 0) . '/' . $randomName;
                $stream = Storage::disk('public')->readStream($rel);
                if ($stream) {
                    Storage::disk('images')->put($destinationPath, $stream);
                    if (is_resource($stream)) { fclose($stream); }
                    return $this->createMediaRecord($tenantId, 'images', $destinationPath);
                }
            }
        }

        return null;
    }

    private function relativePathFromUrl(string $disk, string $value): ?string
    {
        $base = rtrim(Storage::disk($disk)->url('/'), '/');
        if (str_starts_with($value, $base)) {
            $rel = ltrim(substr($value, strlen($base)), '/');
            return $rel ?: null;
        }
        return null;
    }

    private function createMediaRecord(?int $tenantId, string $disk, string $path): ?int
    {
        if (!class_exists(\Nywerk\Media\Models\Media::class)) { return null; }

        // Try derive meta
        $name = basename($path);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $size = 0;
        try { $size = Storage::disk($disk)->size($path) ?: 0; } catch (Throwable $e) {}

        // Create thumbnail via service if possible
        $thumbPath = null;
        try {
            $service = app()->make(\Nywerk\Media\Services\ImagePreviewService::class);
            $thumbPath = $service->createPreviewForFile([
                'name' => $name,
                'extension' => $ext,
                'size' => $size,
            ], $path);
        } catch (Throwable $e) {}

        $media = \Nywerk\Media\Models\Media::create([
            'tenant_id' => $tenantId,
            'type' => 'image',
            'name' => $name,
            'extension' => $ext,
            'path' => $path,
            'thumbnail' => $thumbPath,
            'disk' => $disk,
            'size' => $size,
            'ai_access' => true,
        ]);

        return (int) $media->id;
    }

    public function down(): void
    {
        // not reversible
    }
};


