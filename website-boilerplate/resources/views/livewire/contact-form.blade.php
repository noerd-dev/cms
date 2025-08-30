<?php

use Livewire\Volt\Component;
use Noerd\Website\Models\FormRequest;
use Noerd\Website\Services\RecaptchaService;

new class extends Component {
    
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $message = '';
    public string $recaptchaToken = '';
    public bool $isSubmitting = false;
    public bool $showSuccess = false;
    public string $errorMessage = '';
    
    public function mount()
    {
        // Share reCAPTCHA site key with frontend
        $recaptchaService = new RecaptchaService();
        if ($recaptchaService->isEnabled()) {
            $this->dispatch('recaptcha-site-key', $recaptchaService->getSiteKey());
        }
    }
    
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
    
    protected function messages(): array
    {
        return [
            'name.required' => 'Der Name ist erforderlich.',
            'name.max' => 'Der Name darf maximal 255 Zeichen lang sein.',
            'email.required' => 'Die E-Mail-Adresse ist erforderlich.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'email.max' => 'Die E-Mail-Adresse darf maximal 255 Zeichen lang sein.',
            'phone.max' => 'Die Telefonnummer darf maximal 255 Zeichen lang sein.',
            'message.required' => 'Die Nachricht ist erforderlich.',
            'message.max' => 'Die Nachricht darf maximal 2000 Zeichen lang sein.',
            'recaptchaToken.required' => 'Bitte bestätigen Sie, dass Sie kein Roboter sind.',
        ];
    }
    
    public function submit()
    {
        $this->isSubmitting = true;
        $this->showSuccess = false;
        $this->errorMessage = '';
        
        try {
            $this->validate();
            
            // Verify reCAPTCHA if enabled (but don't require token - fail-open approach)
            $recaptchaService = new RecaptchaService();
            if ($recaptchaService->isEnabled() && !empty($this->recaptchaToken)) {
                if (!$recaptchaService->verify($this->recaptchaToken)) {
                    $this->errorMessage = 'reCAPTCHA-Verifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.';
                    $this->isSubmitting = false;
                    return;
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSubmitting = false;
            throw $e; // Re-throw validation exceptions so Livewire can handle them
        }
        
        // Get tenant_id from session (set by middleware)
        $tenantId = session('selectedTenantId');
                   
        if (!$tenantId) {
            // Try to get tenant via hash from session
            $hash = session('hash');
            if ($hash) {
                $tenant = \Noerd\Website\Models\Tenant::where('hash', $hash)->first();
                $tenantId = $tenant?->id;
                if ($tenantId) {
                    session(['selectedTenantId' => $tenantId]);
                }
            }
        }
        
        if (!$tenantId) {
            $this->errorMessage = 'Fehler: Mandanten-Information fehlt. Bitte laden Sie die Seite neu.';
            $this->isSubmitting = false;
            return;
        }
        
        try {
            FormRequest::create([
                'form' => 'contact',
                'tenant_id' => $tenantId,
                'data' => [
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'message' => $this->message,
                ],
            ]);
            
            $this->reset(['name', 'email', 'phone', 'message', 'recaptchaToken']);
            $this->showSuccess = true;
            
        } catch (\Exception $e) {
            $this->errorMessage = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
            $this->isSubmitting = false;
            return;
        }
        
        $this->isSubmitting = false;
    }
    
    public function resetForm()
    {
        $this->reset(['name', 'email', 'phone', 'message', 'recaptchaToken', 'showSuccess', 'errorMessage']);
        $this->resetErrorBag();
    }
}; ?>

<div class="bg-white p-6 rounded-lg shadow-md max-w-md mx-auto" x-data="{ siteKey: '', recaptchaLoaded: false }">
    <h3 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Kontakt') }}</h3>
    
    @if($showSuccess)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            <p class="font-medium">{{ __('Nachricht erfolgreich gesendet!') }}</p>
            <p class="text-sm">{{ __('Wir werden uns so schnell wie möglich bei Ihnen melden.') }}</p>
            <button wire:click="resetForm" class="mt-2 text-sm text-green-600 hover:text-green-800 underline">
                {{ __('Neue Nachricht senden') }}
            </button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            {{-- Name Field --}}
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Name') }} <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    wire:model="name"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 bg-white placeholder-gray-400 @error('name') border-red-500 @enderror"
                    placeholder="{{ __('Ihr vollständiger Name') }}"
                    required
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            {{-- Email Field --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('E-Mail') }} <span class="text-red-500">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    wire:model="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 bg-white placeholder-gray-400 @error('email') border-red-500 @enderror"
                    placeholder="{{ __('ihre@email.de') }}"
                    required
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            {{-- Phone Field --}}
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Telefon') }}
                </label>
                <input
                    type="tel"
                    id="phone"
                    wire:model="phone"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 bg-white placeholder-gray-400 @error('phone') border-red-500 @enderror"
                    placeholder="{{ __('Ihre Telefonnummer') }}"
                >
                @error('phone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            {{-- Message Field --}}
            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Nachricht') }} <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="message"
                    wire:model="message"
                    rows="4"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 bg-white placeholder-gray-400 @error('message') border-red-500 @enderror"
                    placeholder="{{ __('Ihre Nachricht an uns...') }}"
                    required
                ></textarea>
                @error('message')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            {{-- Hidden reCAPTCHA Token Field --}}
            <input type="hidden" wire:model="recaptchaToken">
            
            {{-- reCAPTCHA Error --}}
            @error('recaptchaToken')
                <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ $message }}
                </div>
            @enderror
            
            {{-- Error Message --}}
            @if($errorMessage)
                <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ $errorMessage }}
                </div>
            @endif
            
            {{-- Submit Button --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
                class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
            >
                <span wire:loading.remove wire:target="submit">{{ __('Nachricht senden') }}</span>
                <span wire:loading wire:target="submit" class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Sende...') }}
                </span>
            </button>
        </form>
    @endif
</div>

@if(config('recaptcha.enabled'))
<script>
    document.addEventListener('livewire:init', function () {
        // Listen for reCAPTCHA site key
        Livewire.on('recaptcha-site-key', (siteKey) => {
            if (siteKey && siteKey.length > 0) {
                loadRecaptcha(siteKey);
            }
        });
        
        // Load reCAPTCHA script
        function loadRecaptcha(siteKey) {
            if (document.querySelector(`script[src*="recaptcha"]`)) return;
            
            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
            script.onload = () => {
                console.log('reCAPTCHA loaded successfully');
            };
            script.onerror = () => {
                console.error('Failed to load reCAPTCHA');
            };
            document.head.appendChild(script);
        }
    });
</script>
@endif
