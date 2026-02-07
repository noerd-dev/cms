<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Models\FormRequest;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    #[Url(as: 'formRequestId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = FormRequest::class;

    public array $formRequestData = [];

    public function mount(mixed $model = null): void
    {
        $this->initDetail($model);

        $formRequest = new FormRequest;
        if ($this->modelId) {
            $formRequest = FormRequest::find($this->modelId) ?? new FormRequest;
        }

        // Prepare view model
        $this->formRequestData = [
            'id' => $formRequest->id,
            'tenant_id' => $formRequest->tenant_id,
            'created_at' => $formRequest->created_at,
            'updated_at' => $formRequest->updated_at,
            'data' => is_string($formRequest->data) ? json_decode($formRequest->data, true) : ($formRequest->data ?? []),
        ];
    }

    public function delete(): void
    {
        $fr = FormRequest::find($this->modelId);
        if ($fr) {
            $fr->delete();
        }
        $this->closeModalProcess($this->getListComponent());
    }

    #[On('languageChanged')]
    public function languageChanged(): void
    {
        $this->dispatch('$refresh');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Form Request') }} #{{$formRequestData['id'] ?? ''}}</x-noerd::modal-title>
    </x-slot:header>

    <div class="p-4 border border-b-gray-200 mb-4 sm:p-8 relative overflow-hidden rounded-lg bg-gray-950/[2.5%] after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-gray-950/5">
        <div class="text-sm text-gray-600 mb-4">
            <div><strong>{{ __('Created') }}:</strong>
                {{\Carbon\Carbon::parse($formRequestData['created_at'])->format('d.m.Y H:i')}}
            </div>
        </div>

        <div class="bg-white rounded border p-4">
            <div class="font-semibold mb-2">{{ __('Data') }}</div>
            @php($data = $formRequestData['data'] ?? [])
            @if(is_array($data))
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($data as $key => $value)
                        <div>
                            <dt class="text-gray-500 text-xs uppercase tracking-wide">{{$key}}</dt>
                            <dd class="text-sm mt-1">
                                @if(is_array($value))
                                    <pre class="bg-gray-50 p-2 rounded text-xs whitespace-pre-wrap">{{ json_encode($value, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                                @else
                                    {{$value}}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <pre class="bg-gray-50 p-4 rounded text-xs whitespace-pre-wrap">{{ json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$modelId" />
    </x-slot:footer>
</x-noerd::page>
















