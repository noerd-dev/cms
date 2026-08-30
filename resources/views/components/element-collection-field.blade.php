<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Facades\Noerd;

new class extends Component
{
    public string $ownerType = ElementCollectionService::OWNER_PAGE;

    public mixed $ownerId = null;

    public string $fieldName = '';

    public string $label = '';

    public array $rowFields = [];

    public function mount(
        string $ownerType = ElementCollectionService::OWNER_PAGE,
        mixed $ownerId = null,
        string $fieldName = '',
        string $label = '',
        array $rowFields = [],
    ): void {
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->fieldName = $fieldName;
        $this->label = $label;
        $this->rowFields = $rowFields;
    }

    /**
     * Create the element collection on demand, then open its entries list in a modal.
     */
    public function manage(): void
    {
        if (! $this->ownerId) {
            return;
        }

        [$tenantId, $ownerName] = $this->resolveOwnerContext();

        if ($tenantId === null) {
            return;
        }

        $service = app(ElementCollectionService::class);

        $elementCollection = $service->ensure(
            $this->ownerType,
            (int) $this->ownerId,
            (int) $tenantId,
            $this->fieldName,
            $this->rowFields,
            $service->displayName($this->label, $ownerName),
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
     * Resolve [tenantId, ownerName] for the current owner.
     *
     * @return array{0: int|null, 1: string}
     */
    private function resolveOwnerContext(): array
    {
        if ($this->ownerType === ElementCollectionService::OWNER_ELEMENT_PAGE) {
            $elementPage = ElementPage::find($this->ownerId);

            if (! $elementPage) {
                return [null, ''];
            }

            $title = FieldHelper::getElementFields($elementPage->element_key)['title'] ?? $elementPage->element_key;

            return [$elementPage->page?->tenant_id, (string) $title];
        }

        $page = Page::find($this->ownerId);

        if (! $page) {
            return [null, ''];
        }

        $names = is_array($page->name) ? $page->name : [];
        $entryName = $names['de'] ?? (reset($names) ?: '');

        return [$page->tenant_id, (string) $entryName];
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
