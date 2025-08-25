<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'cms-settings-component';

    public $form = [
        'homepage_page_id' => null,
    ];

    public function mount(): void
    {
        $tenantId = auth()->user()?->selected_tenant_id;
        $settings = CmsSetting::query()->firstOrCreate(['tenant_id' => $tenantId]);
        $this->form['homepage_page_id'] = $settings->homepage_page_id;
    }

    public function store(): void
    {
        $tenantId = auth()->user()?->selected_tenant_id;

        $this->validate([
            'form.homepage_page_id' => ['nullable', 'exists:pages,id'],
        ]);

        CmsSetting::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            ['homepage_page_id' => $this->form['homepage_page_id']]
        );

        $this->dispatch('toast', [
            'title' => 'Gespeichert',
            'description' => 'Die Einstellungen wurden gespeichert.',
        ]);
    }

    public function formatName($value): string
    {
        // Accept array or JSON-string, fallback to raw string
        $names = [];
        if (is_array($value)) {
            $names = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $names = $decoded;
            } else {
                return $value;
            }
        }

        $parts = [];
        foreach ($names as $lang => $text) {
            if (is_string($text) && $text !== '') {
                $parts[] = $lang . ': ' . $text;
            }
        }

        return implode(' ', $parts);
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Einstellungen</x-noerd::modal-title>
    </x-slot:header>

    <div class="grid gap-6">
        <div>
            <x-noerd::title>Startseite</x-noerd::title>
            <div class="mt-2">
                <select wire:model="form.homepage_page_id" class="border rounded px-3 py-2 w-full">
                    <option value="">- Keine ausgewählt -</option>
                    @foreach(Page::orderBy('name')->get() as $p)
                        <option value="{{$p->id}}">{{$this->formatName($p->name)}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar showDelete="false"/>
    </x-slot:footer>
</x-noerd::page>


