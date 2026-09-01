<?php

use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Noerd\Website\Models\FormRequest;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;

new class extends Component {

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $message = '';
    public string $turnstileToken = '';
    public bool $isSubmitting = false;
    public bool $showSuccess = false;
    public string $errorMessage = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ];

        if (config('services.turnstile.enabled')) {
            $rules['turnstileToken'] = ['required', new Turnstile];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('The name is required.'),
            'name.max' => __('The name may not be longer than 255 characters.'),
            'email.required' => __('The email address is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.max' => __('The email address may not be longer than 255 characters.'),
            'phone.max' => __('The phone number may not be longer than 255 characters.'),
            'message.required' => __('The message is required.'),
            'message.max' => __('The message may not be longer than 2000 characters.'),
            'turnstileToken.required' => __('Please confirm that you are not a robot.'),
        ];
    }

    public function submit()
    {
        $this->isSubmitting = true;
        $this->showSuccess = false;
        $this->errorMessage = '';

        try {
            $this->validate();
        } catch (ValidationException $e) {
            // Turnstile tokens are single-use; clearing it re-renders the widget.
            $this->reset('turnstileToken');
            $this->isSubmitting = false;

            throw $e; // Re-throw validation exceptions so Livewire can handle them
        }

        // Get tenant_id from session (set by middleware)
        $tenantId = session('selectedTenantId');

        if (!$tenantId) {
            // Try to get tenant via uuid from session
            $uuid = session('uuid');
            if ($uuid) {
                $tenant = \Noerd\Website\Models\Tenant::where('uuid', $uuid)->first();
                $tenantId = $tenant?->id;
                if ($tenantId) {
                    session(['selectedTenantId' => $tenantId]);
                }
            }
        }

        if (!$tenantId) {
            $this->errorMessage = __('Error: Tenant information is missing. Please reload the page.');
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

            $this->reset(['name', 'email', 'phone', 'message', 'turnstileToken']);
            $this->showSuccess = true;

        } catch (\Exception $e) {
            $this->errorMessage = __('An error occurred. Please try again later.');
            $this->isSubmitting = false;
            return;
        }

        $this->isSubmitting = false;
    }

    public function resetForm()
    {
        $this->reset(['name', 'email', 'phone', 'message', 'turnstileToken', 'showSuccess', 'errorMessage']);
        $this->resetErrorBag();
    }
}; ?>

<div class="bg-white p-6 rounded-lg shadow-md max-w-md mx-auto">
    <h3 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Contact') }}</h3>

    @if($showSuccess)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            <p class="font-medium">{{ __('Message sent successfully!') }}</p>
            <p class="text-sm">{{ __('We will get back to you as soon as possible.') }}</p>
            <button wire:click="resetForm" class="mt-2 text-sm text-green-600 hover:text-green-800 underline">
                {{ __('Send a new message') }}
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
                    placeholder="{{ __('Your full name') }}"
                    required
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Email Field --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Email') }} <span class="text-red-500">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    wire:model="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 bg-white placeholder-gray-400 @error('email') border-red-500 @enderror"
                    placeholder="{{ __('your@email.com') }}"
                    required
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Phone Field --}}
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Phone') }}
                </label>
                <input
                    type="tel"
                    id="phone"
                    wire:model="phone"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 bg-white placeholder-gray-400 @error('phone') border-red-500 @enderror"
                    placeholder="{{ __('Your phone number') }}"
                >
                @error('phone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Message Field --}}
            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Message') }} <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="message"
                    wire:model="message"
                    rows="4"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 bg-white placeholder-gray-400 @error('message') border-red-500 @enderror"
                    placeholder="{{ __('Your message to us...') }}"
                    required
                ></textarea>
                @error('message')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Cloudflare Turnstile --}}
            @if (config('services.turnstile.enabled'))
                <x-turnstile id="footer_contact" wire:model="turnstileToken" />
            @endif

            @error('turnstileToken')
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
                <span wire:loading.remove wire:target="submit">{{ __('Send message') }}</span>
                <span wire:loading wire:target="submit" class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Sending...') }}
                </span>
            </button>
        </form>
    @endif
</div>
