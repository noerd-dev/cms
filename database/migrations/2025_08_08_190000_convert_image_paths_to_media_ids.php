<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Migrate Collections
        try {
            $this->migrateCollections();
        } catch (Throwable $e) {
            // swallow to not break deployment; consider logging
        }

        // Migrate Element Pages
        try {
            $this->migrateElementPages();
        } catch (Throwable $e) {
            // swallow to not break deployment; consider logging
        }
    }

    public function down(): void
    {
        // no-op: cannot reliably revert ids back to urls
    }

    private function migrateCollections(): void
    {
        if (!class_exists(\Noerd\Cms\Models\Collection::class)) {
            return;
        }
        $collections = \Noerd\Cms\Models\Collection::query()->get();

        foreach ($collections as $collection) {
            $data = json_decode($collection->data ?? '[]', true) ?: [];
            $key = mb_strtolower($collection->collection_key ?? '');

            // Determine image fields from YAML config
            $imageFieldNames = [];
            try {
                $fieldsConfig = \Noerd\Cms\Helpers\CollectionHelper::getCollectionFields($key);
                foreach (($fieldsConfig['fields'] ?? []) as $field) {
                    if (($field['type'] ?? null) === 'image') {
                        $name = $field['name'] ?? null;
                        if ($name) {
                            $imageFieldNames[] = str_replace('model.', '', $name);
                        }
                    }
                }
            } catch (Throwable $e) {
                // skip on missing YAML
                $imageFieldNames = [];
            }

            if (empty($imageFieldNames)) {
                continue;
            }

            $changed = false;
            foreach ($imageFieldNames as $fieldName) {
                $val = $data[$fieldName] ?? null;
                if (is_string($val) && $val !== '') {
                    $mediaId = $this->resolveMediaIdFromValue($collection->tenant_id, $val);
                    if ($mediaId) {
                        $data[$fieldName] = $mediaId;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $collection->data = json_encode($data);
                $collection->save();
            }
        }
    }

    private function migrateElementPages(): void
    {
        if (!class_exists(\Noerd\Cms\Models\ElementPage::class)) {
            return;
        }
        $elementPages = \Noerd\Cms\Models\ElementPage::query()->with('page')->get();

        foreach ($elementPages as $elementPage) {
            $data = json_decode($elementPage->data ?? '[]', true) ?: [];
            $tenantId = optional($elementPage->page)->tenant_id;

            // Determine image fields from element YAML
            $imageFieldNames = [];
            try {
                $fieldsConfig = \Noerd\Cms\Helpers\FieldHelper::getElementFields($elementPage->element_key);
                foreach (($fieldsConfig['fields'] ?? []) as $field) {
                    if (($field['type'] ?? null) === 'image') {
                        $name = $field['name'] ?? null;
                        if ($name) {
                            $imageFieldNames[] = str_replace('model.', '', $name);
                        }
                    }
                }
            } catch (Throwable $e) {
                // skip on missing YAML
                $imageFieldNames = [];
            }

            if (empty($imageFieldNames)) {
                continue;
            }

            $changed = false;
            foreach ($imageFieldNames as $fieldName) {
                $val = $data[$fieldName] ?? null;
                if (is_string($val) && $val !== '') {
                    $mediaId = $this->resolveMediaIdFromValue($tenantId, $val);
                    if ($mediaId) {
                        $data[$fieldName] = $mediaId;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $elementPage->data = json_encode($data);
                $elementPage->save();
            }
        }
    }

    private function resolveMediaIdFromValue(?int $tenantId, string $value): ?int
    {
        if (!class_exists(\Noerd\Media\Models\Media::class)) {
            return null;
        }

        // Try by matching URL generated from media path
        $query = \Noerd\Media\Models\Media::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Preload and map urls to ids
        $medias = $query->select(['id','disk','path'])->get();
        foreach ($medias as $media) {
            try {
                $url = \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path);
                if ($url === $value) {
                    return (int) $media->id;
                }
            } catch (Throwable $e) {
                // ignore disk errors
            }
        }

        // Try simple path containment heuristic
        foreach ($medias as $media) {
            if (str_contains($value, (string) $media->path)) {
                return (int) $media->id;
            }
        }

        return null;
    }
};
