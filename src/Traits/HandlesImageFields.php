<?php

declare(strict_types=1);

namespace Noerd\Cms\Traits;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Facades\Noerd;
use Noerd\Support\LayoutFields;

/**
 * The `type: image` fields of a CMS detail: the core image element binds a
 * plain upload to `imageUploads.{field}` and calls `openSelectMediaModal()`
 * and `deleteImage()` — this trait answers all three and the picker's
 * `mediaSelected` event, modelled on the core's setup-collection-detail.
 *
 * Every path is client input and is checked on the server: only the image
 * fields the SERVER-SIDE layout declares (`imageFieldNames()`) take a value,
 * only a user who may save the record writes one (`canSaveObject()`), an
 * upload must be a raster image within the size limit, and a picked media id
 * must exist in the tenant (the resolver answers within the tenant scope).
 * The field stores the MEDIA ID, never a URL — the media disk mirrors the
 * library's folder tree, so a path is only true until the file is moved.
 */
trait HandlesImageFields
{
    /** Plain uploads of the image fields (`imageUploads.{field}`). */
    public array $imageUploads = [];

    /**
     * Token of the media picker this component opened: a `mediaSelected`
     * answer is adopted only with it. Server state, never part of the record.
     */
    #[Locked]
    public ?string $mediaSelectToken = null;

    /**
     * The detailData keys of the image fields the server-side layout declares
     * — the only keys an upload, a pick or a removal may write.
     *
     * @return list<string>
     */
    abstract protected function imageFieldNames(): array;

    public function updatedImageUploads(): void
    {
        if (! $this->canSaveObject()) {
            $this->imageUploads = [];

            return;
        }

        $imageFields = $this->imageFieldNames();
        $resolver = app(MediaResolverContract::class);

        foreach ($this->imageUploads as $fieldName => $uploadedFile) {
            if (! in_array((string) $fieldName, $imageFields, true)) {
                unset($this->imageUploads[$fieldName]);

                continue;
            }

            if (! $uploadedFile) {
                continue;
            }

            $this->validate([
                'imageUploads.' . $fieldName => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:' . $this->maxImageKilobytes()],
            ]);

            $reference = $resolver->storeUploadedFileReference($uploadedFile);

            if ($reference !== null && $reference !== '') {
                $this->detailData[$fieldName] = $reference;
            }

            $this->imageUploads[$fieldName] = null;
        }
    }

    public function deleteImage(string $fieldName): void
    {
        if (! $this->canSaveObject() || ! in_array($fieldName, $this->imageFieldNames(), true)) {
            return;
        }

        $this->detailData[$fieldName] = null;
    }

    public function openSelectMediaModal(string $fieldName): void
    {
        $picker = app(MediaResolverContract::class)->pickerComponent();

        if ($picker === null || ! $this->canSaveObject() || ! in_array($fieldName, $this->imageFieldNames(), true)) {
            return;
        }

        $this->mediaSelectToken = uniqid('media_', true);

        Noerd::modal($picker, ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $this->mediaSelectToken]);
    }

    /**
     * The picker's answer — for the field this component asked for.
     */
    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if ($this->mediaSelectToken === null || $this->mediaSelectToken !== $token || ! $this->canSaveObject()) {
            return;
        }

        $fieldName ??= 'image';

        if (! in_array($fieldName, $this->imageFieldNames(), true) || ! app(MediaResolverContract::class)->exists($mediaId)) {
            return;
        }

        $this->detailData[$fieldName] = $mediaId;
        $this->mediaSelectToken = null;
    }

    /**
     * The detailData keys of the `type: image` fields of a field layout
     * (recursing into blocks).
     *
     * @param  array<int, mixed>  $fields
     * @return list<string>
     */
    protected function imageFieldNamesOf(array $fields): array
    {
        $names = [];

        LayoutFields::walk($fields, function (array $field) use (&$names): void {
            $name = $field['name'] ?? null;

            if (($field['type'] ?? null) === 'image' && is_string($name) && $name !== '') {
                $key = str_starts_with($name, 'detailData.') ? mb_substr($name, mb_strlen('detailData.')) : $name;

                if ($key !== '' && ! str_contains($key, '.')) {
                    $names[] = $key;
                }
            }
        });

        return array_values(array_unique($names));
    }

    /**
     * The upload limit in KB: the media module's when it sets one.
     */
    private function maxImageKilobytes(): int
    {
        return (int) config('media.max_upload_size', 10240);
    }
}
