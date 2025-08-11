<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        try { $this->collections(); } catch (Throwable $e) {}
        try { $this->elementPages(); } catch (Throwable $e) {}
    }

    private function collections(): void
    {
        if (!class_exists(\Noerd\Cms\Models\Collection::class)) { return; }
        $rows = \Noerd\Cms\Models\Collection::query()->get();
        foreach ($rows as $row) {
            $data = json_decode($row->data ?? '[]', true) ?: [];
            $key = strtolower($row->collection_key ?? '');

            $imageFields = [];
            try {
                $cfg = \Noerd\Cms\Helpers\CollectionHelper::getCollectionFields($key);
                foreach (($cfg['fields'] ?? []) as $field) {
                    if (($field['type'] ?? null) === 'image' && !empty($field['name'])) {
                        $imageFields[] = str_replace('model.', '', $field['name']);
                    }
                }
            } catch (Throwable $e) {}

            if (empty($imageFields)) { continue; }

            $changed = false;
            foreach ($imageFields as $fname) {
                $val = $data[$fname] ?? null;
                if (is_string($val) && str_starts_with($val, '@')) {
                    $data[$fname] = ltrim($val, '@');
                    $changed = true;
                }
            }
            if ($changed) { $row->data = json_encode($data); $row->save(); }
        }
    }

    private function elementPages(): void
    {
        if (!class_exists(\Noerd\Cms\Models\ElementPage::class)) { return; }
        $rows = \Noerd\Cms\Models\ElementPage::query()->get();
        foreach ($rows as $row) {
            $data = json_decode($row->data ?? '[]', true) ?: [];

            $imageFields = [];
            try {
                $cfg = \Noerd\Cms\Helpers\FieldHelper::getElementFields($row->element_key);
                foreach (($cfg['fields'] ?? []) as $field) {
                    if (($field['type'] ?? null) === 'image' && !empty($field['name'])) {
                        $imageFields[] = str_replace('model.', '', $field['name']);
                    }
                }
            } catch (Throwable $e) {}

            if (empty($imageFields)) { continue; }

            $changed = false;
            foreach ($imageFields as $fname) {
                $val = $data[$fname] ?? null;
                if (is_string($val) && str_starts_with($val, '@')) {
                    $data[$fname] = ltrim($val, '@');
                    $changed = true;
                }
            }
            if ($changed) { $row->data = json_encode($data); $row->save(); }
        }
    }

    public function down(): void {}
};


