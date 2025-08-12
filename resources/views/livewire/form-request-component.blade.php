<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Models\FormRequest;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'form-request-component';
    public const LIST_COMPONENT = 'form-requests-table';
    public const ID = 'formRequestId';

    #[Url(keep: false, except: '')]
    public ?string $formRequestId = null;

    public array $model = [];
    public FormRequest $formRequestModel;

    public function mount(FormRequest $formRequest): void
    {
        if ($this->modelId) {
            $formRequest = FormRequest::find($this->modelId);
        }

        $this->modelId = $formRequest->id;
        $this->formRequestModel = $formRequest;

        // Prepare view model
        $this->model = [
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
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    #[On('languageChanged')]
    public function languageChanged(): void
    {
        $this->dispatch('$refresh');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Form Request') }} #{{$model['id'] ?? ''}}</x-noerd::modal-title>
    </x-slot:header>

    <div class="p-4 border border-b-gray-200 mb-4 sm:p-8 relative overflow-hidden rounded-lg bg-gray-950/[2.5%] after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-gray-950/5 dark:after:inset-ring-white/10">
        <div class="text-sm text-gray-600 mb-4">
            <div><strong>ID:</strong> {{$model['id']}}</div>
            <div><strong>Tenant:</strong> {{$model['tenant_id']}}</div>
            <div><strong>{{ __('Created') }}:</strong> {{$model['created_at']}}</div>
        </div>

        <div class="bg-white rounded border p-4">
            <div class="font-semibold mb-2">{{ __('Data') }}</div>
            @php($data = $model['data'] ?? [])
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


