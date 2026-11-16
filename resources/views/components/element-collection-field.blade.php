<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Facades\Noerd;
use Noerd\Helpers\AccessHelper;
use Noerd\Support\RelationFieldDefinition;

/*
 | The `elementCollection` field of a collection entry (or a page element):
 | the count of its rows and the button that opens them. It has no route — the
 | modal stack mounts it with what the browser names — so it asks for the CMS
 | app and the page permissions itself. Owner and field name are locked, and
 | the row schema of a NEW element collection comes from the owner's server-side
 | definition (the collection definition or the element YAML), never from the
 | mount arguments.
 */
new class extends Component {
    #[Locked]
    public string $ownerType = ElementCollectionService::OWNER_PAGE;

    #[Locked]
    public mixed $ownerId = null;

    #[Locked]
    public string $fieldName = '';

    #[Locked]
    public string $label = '';

    /**
     * Accepted for the field-type resolver's mount arguments; the schema that is
     * stored comes from the owner's definition (see ownerContext()).
     */
    public function mount(
        string $ownerType = ElementCollectionService::OWNER_PAGE,
        mixed $ownerId = null,
        string $fieldName = '',
        string $label = '',
        array $rowFields = [],
    ): void {
        abort_unless(AccessHelper::canUseApp('CMS') && AccessHelper::canReadObject(Page::class), 403);

        $this->ownerType = in_array($ownerType, [ElementCollectionService::OWNER_PAGE, ElementCollectionService::OWNER_ELEMENT_PAGE], true)
            ? $ownerType
            : ElementCollectionService::OWNER_PAGE;
        $this->ownerId = is_numeric($ownerId) ? (int) $ownerId : null;
        $this->fieldName = str_replace('detailData.', '', $fieldName);
        $this->label = $label;
    }

    /**
     * Create the element collection on demand, then open its entries list in a modal.
     */
    public function manage(): void
    {
        // Creating the element collection writes to the owning page's data.
        if (! $this->ownerId || ! AccessHelper::canUseApp('CMS') || ! AccessHelper::canWriteObject(Page::class)) {
            return;
        }

        $context = $this->ownerContext();

        if ($context === null) {
            return;
        }

        $service = app(ElementCollectionService::class);

        $elementCollection = $service->ensure(
            $this->ownerType,
            (int) $this->ownerId,
            $context['tenantId'],
            $this->fieldName,
            $context['rowFields'],
            $service->displayName($context['label'], $context['ownerName']),
        );

        Noerd::modal('cms::collection-entries-list', [
            'collectionKey' => $elementCollection->collection_key,
            'elementCollection' => true,
        ]);
    }

    #[On('refreshList-element-collection-field')]
    public function refreshCount(): void
    {
        // No-op: the roundtrip re-renders the component and recomputes the entry count.
    }

    #[Computed]
    public function elementCollection(): ?Collection
    {
        if (! $this->ownerId) {
            return null;
        }

        return Collection::query()
            ->where('collection_key', app(ElementCollectionService::class)->keyFor($this->ownerType, (int) $this->ownerId, $this->fieldName))
            ->where('is_element_collection', true)
            ->first();
    }

    #[Computed]
    public function entryCount(): int
    {
        return (int) ($this->elementCollection?->rows()->count() ?? 0);
    }

    /**
     * The owner in the current tenant and the definition of its field: a
     * collection entry and the `elementCollection` field of its collection
     * definition, or a page element and the field of its element YAML. Null
     * when the owner is not of this tenant or declares no such field.
     *
     * @return array{tenantId: int, ownerName: string, label: string, rowFields: array<int, array<string, mixed>>}|null
     */
    private function ownerContext(): ?array
    {
        if ($this->ownerType === ElementCollectionService::OWNER_ELEMENT_PAGE) {
            $element = ContentElement::query()->find($this->ownerId);
            // Tenant guard: the owning content resolves through the tenant scope.
            $content = $element?->content()->first();

            if (! $element || ! $content) {
                return null;
            }

            $elementFields = FieldHelper::getElementFields((string) $element->element_key);
            $definition = $this->collectionField(FieldHelper::flattenFields($elementFields['fields'] ?? []));

            return $definition === null ? null : [
                'tenantId' => (int) $content->tenant_id,
                'ownerName' => (string) ($elementFields['title'] ?? $element->element_key),
                'label' => (string) ($definition['label'] ?? $this->fieldName),
                'rowFields' => is_array($definition['fields'] ?? null) ? $definition['fields'] : [],
            ];
        }

        $page = Page::query()->find($this->ownerId);
        $collection = $page?->collection_id ? Collection::query()->find($page->collection_id) : null;

        if (! $page || ! $collection) {
            return null;
        }

        $layout = CollectionHelper::getCollectionFields(mb_strtolower((string) $collection->collection_key));
        $definition = $this->collectionField(FieldHelper::flattenFields($layout['fields'] ?? []));

        return $definition === null ? null : [
            'tenantId' => (int) $page->tenant_id,
            'ownerName' => (string) RelationFieldDefinition::normalizeDisplayValue(is_array($page->name) ? $page->name : []),
            'label' => (string) ($definition['label'] ?? $this->fieldName),
            'rowFields' => is_array($definition['fields'] ?? null) ? $definition['fields'] : [],
        ];
    }

    /**
     * The `elementCollection` field named $fieldName among $fields.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>|null
     */
    private function collectionField(array $fields): ?array
    {
        foreach ($fields as $field) {
            $name = preg_replace('/^(detailData|model)\./', '', (string) ($field['name'] ?? ''));

            if ($name === $this->fieldName && ($field['type'] ?? null) === 'elementCollection') {
                return $field;
            }
        }

        return null;
    }
}; ?>

<div>
    <x-noerd::input-label :value="__($label)"/>

    @if($ownerId)
        <x-noerd::button
            type="button"
            wire:click="manage"
            class="!mt-0 inline-flex items-center gap-2"
        >
            <x-noerd::icons.list-bullet class="w-5 h-5"/>
            <span>{{ $this->entryCount }} {{ trans_choice('Entry|Entries', $this->entryCount) }} — {{ __('Manage') }}</span>
        </x-noerd::button>
    @else
        <p class="text-sm text-zinc-500">{{ __('Please save the entry first to manage its entries.') }}</p>
    @endif
</div>
