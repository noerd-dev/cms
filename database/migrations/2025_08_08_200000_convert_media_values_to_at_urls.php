<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class () extends Migration {
    public function up(): void
    {
        try {
            $this->collectionsToAtUrls();
        } catch (Throwable $e) {
        }
        try {
            $this->elementPagesToAtUrls();
        } catch (Throwable $e) {
        }
    }

    public function down(): void {}

    private function collectionsToAtUrls(): void
    {
        if (!class_exists(\Noerd\Cms\Models\Collection::class)) {
            return;
        }
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
            } catch (Throwable $e) {
            }

            if (empty($imageFields)) {
                continue;
            }

            $changed = false;
            foreach ($imageFields as $fname) {
                $val = $data[$fname] ?? null;
                $url = $this->resolveAtUrl($collection->tenant_id, $val);
                if ($url) {
                    $data[$fname] = $url;
                    $changed = true;
                }
            }
            if ($changed) {
                $collection->data = json_encode($data);
                $collection->save();
            }
        }
    }

    private function elementPagesToAtUrls(): void
    {
        if (!class_exists(\Noerd\Cms\Models\ElementPage::class)) {
            return;
        }
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
            } catch (Throwable $e) {
            }

            if (empty($imageFields)) {
                continue;
            }

            $changed = false;
            foreach ($imageFields as $fname) {
                $val = $data[$fname] ?? null;
                $url = $this->resolveAtUrl($tenantId, $val);
                if ($url) {
                    $data[$fname] = $url;
                    $changed = true;
                }
            }
            if ($changed) {
                $elementPage->data = json_encode($data);
                $elementPage->save();
            }
        }
    }

    private function resolveAtUrl(?int $tenantId, $value): ?string
    {
        // already correct
        if (is_string($value) && str_starts_with($value, '@http')) {
            return $value;
        }

        if (is_numeric($value)) {
            $media = \Noerd\Media\Models\Media::find((int) $value);
            if ($media) {
                try {
                    $url = Storage::disk($media->disk)->url($media->thumbnail ?? $media->path);
                } catch (Throwable $e) {
                    $url = null;
                }
                return $url ? '@' . $url : null;
            }
            return null;
        }

        if (is_string($value) && $value !== '') {
            // value is URL/path; try to convert to canonical @url
            $mediaId = $this->tryFindMediaIdByString($tenantId, $value);
            if ($mediaId) {
                $media = \Noerd\Media\Models\Media::find($mediaId);
                if ($media) {
                    try {
                        $url = Storage::disk($media->disk)->url($media->thumbnail ?? $media->path);
                    } catch (Throwable $e) {
                        $url = null;
                    }
                    return $url ? '@' . $url : null;
                }
            }
            // fallback: if looks like storage URL, prefix with '@'
            if (str_starts_with($value, 'http') || str_starts_with($value, '/storage/')) {
                if (str_starts_with($value, '/storage/')) {
                    $rel = mb_substr($value, mb_strlen('/storage/'));
                    try {
                        $url = Storage::disk('public')->url($rel);
                        return '@' . $url;
                    } catch (Throwable $e) {
                    }
                }
                return '@' . $value;
            }
        }
        return null;
    }

    private function tryFindMediaIdByString(?int $tenantId, string $value): ?int
    {
        $query = \Noerd\Media\Models\Media::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $medias = $query->select(['id','disk','path'])->get();
        foreach ($medias as $media) {
            try {
                if (Storage::disk($media->disk)->url($media->path) === $value) {
                    return (int) $media->id;
                }
            } catch (Throwable $e) {
            }
        }
        foreach ($medias as $media) {
            if (str_contains($value, (string) $media->path)) {
                return (int) $media->id;
            }
        }
        return null;
    }
};
