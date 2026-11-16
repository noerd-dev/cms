@props(['title' => null, 'meta' => []])

@php
    // Cookie banner and Google tag of the tenant — the ONE gate is the CMS's
    // WebsiteTrackingService (the tag only with the banner, after consent and
    // on a host that shares no session with the backend).
    $websiteTenantId = (int) (request()->attributes->get('tenant_id') ?? 0);
    $tracking = $websiteTenantId > 0
        ? app(\Noerd\Cms\Services\WebsiteTrackingService::class)->forRequest($websiteTenantId, request()->getHost())
        : null;

    // Navigation active state logic - inline to avoid function redeclaration errors
    $currentUrl = request()->url();
    $currentPath = parse_url($currentUrl, PHP_URL_PATH);

    // Page meta (Noerd\Cms\Support\PageMeta::of()): meta_title, meta_description,
    // meta_noindex and the Open Graph image (an absolute delivery URL).
    $meta = is_array($meta) ? $meta : [];
    $pageTitle = $meta['title'] ?? $title ?? ($globals['siteTitle'] ?? 'CMS Frontend');
    $turnstileEnabled = config('services.turnstile.enabled')
        && class_exists('RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile');
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    @if (! empty($meta['description']))
        <meta name="description" content="{{ $meta['description'] }}">
    @endif
    @if (! empty($meta['noindex']))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta property="og:title" content="{{ $pageTitle }}">
    @if (! empty($meta['description']))
        <meta property="og:description" content="{{ $meta['description'] }}">
    @endif
    @if (! empty($meta['image']))
        <meta property="og:image" content="{{ $meta['image'] }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- Cloudflare Turnstile is optional: only with the package installed and configured.
         A dynamic component, because a static <x-turnstile…> tag is resolved
         when the view compiles and fails without the package. --}}
    @if ($turnstileEnabled)
        <x-dynamic-component component="turnstile.scripts" />
    @endif
    @include('website::partials.tracking-head', ['tracking' => $tracking])
</head>
<body class="min-h-screen flex flex-col bg-gray-50 text-gray-900">

<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center">
                <a href="{{ route('website.index', ['uuid' => request('uuid')]) }}" class="flex items-center">
                    <span class="font-semibold">{{ $globals['siteTitle'] ?? 'CMS Frontend' }}</span>
                </a>
                <div class="hidden md:block ml-8">
                    <div class="flex space-x-4">
                        @forelse(($navigation['main'] ?? []) as $link)
                            @php
                                $linkPath = parse_url($link['href'], PHP_URL_PATH);

                                // Handle home page case - both should be '/' or website route
                                if ($linkPath === '/' || str_contains($link['href'], 'website')) {
                                    $isActive = $currentPath === '/' || str_contains($currentUrl, 'website');
                                } else {
                                    $isActive = $currentPath === $linkPath;
                                }

                                $activeClasses = $isActive
                                    ? 'bg-gray-900 text-white'
                                    : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900';
                            @endphp
                            <div class="relative group">
                                <a href="{{ \Noerd\Cms\Support\SafeUrl::href($link['href'] ?? null) }}"
                                   @if(($link['new_tab'] ?? false)) target="_blank" rel="noopener noreferrer" @endif
                                   class="block rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 {{ $activeClasses }}">
                                    {{ $link['name'] }}
                                </a>
                                @if (! empty($link['children']))
                                    <div class="absolute left-0 z-10 hidden min-w-48 rounded-md border border-gray-200 bg-white py-1 shadow-sm group-hover:block group-focus-within:block">
                                        @foreach ($link['children'] as $child)
                                            <a href="{{ \Noerd\Cms\Support\SafeUrl::href($child['href'] ?? null) }}"
                                               @if(($child['new_tab'] ?? false)) target="_blank" rel="noopener noreferrer" @endif
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                {{ $child['name'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <a href="#" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900">Home</a>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="hidden md:flex items-center space-x-4">
                <livewire:website::frontend-language-switcher/>
            </div>

            <div class="md:hidden">
                <button id="mobile-menu-button" type="button" onclick="document.getElementById('mobile-menu').classList.toggle('hidden'); this.setAttribute('aria-expanded', document.getElementById('mobile-menu').classList.contains('hidden') ? 'false' : 'true');" class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-black" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">{{ __('Open menu') }}</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div class="md:hidden hidden" id="mobile-menu">
        <div class="space-y-1 px-2 pt-2 pb-3">
            @foreach(($navigation['main'] ?? []) as $link)
                @php
                    $linkPath = parse_url($link['href'], PHP_URL_PATH);

                    // Handle home page case - both should be '/' or website route
                    if ($linkPath === '/' || str_contains($link['href'], 'website')) {
                        $isActive = $currentPath === '/' || str_contains($currentUrl, 'website');
                    } else {
                        $isActive = $currentPath === $linkPath;
                    }

                    $mobileActiveClasses = $isActive
                        ? 'bg-gray-900 text-white'
                        : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900';
                @endphp
                <a href="{{ \Noerd\Cms\Support\SafeUrl::href($link['href'] ?? null) }}"
                   @if(($link['new_tab'] ?? false)) target="_blank" rel="noopener noreferrer" @endif
                   class="block rounded-md px-3 py-2 text-base font-medium transition-colors duration-200 {{ $mobileActiveClasses }}">
                    {{ $link['name'] }}
                </a>
                @foreach (($link['children'] ?? []) as $child)
                    <a href="{{ \Noerd\Cms\Support\SafeUrl::href($child['href'] ?? null) }}"
                       @if(($child['new_tab'] ?? false)) target="_blank" rel="noopener noreferrer" @endif
                       class="block rounded-md py-2 pr-3 pl-6 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                        {{ $child['name'] }}
                    </a>
                @endforeach
            @endforeach
            <div class="border-t border-gray-200 my-2"></div>
            <div class="px-3 py-2">
                @livewire('website::frontend-language-switcher', key('language-switcher-mobile'))
            </div>
        </div>
    </div>
</nav>

<main class="flex-1">
    {{ $slot }}
</main>

<footer class="bg-gray-800 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-3 gap-8">
            <!-- Navigation Links -->
            <div>
                <h3 class="text-lg font-semibold mb-4">{{ __('Navigation') }}</h3>
                <div class="flex flex-col gap-2">
                    @foreach(($navigation['footer'] ?? []) as $link)
                        @php
                            $linkPath = parse_url($link['href'], PHP_URL_PATH);

                            // Handle home page case - both should be '/' or website route
                            if ($linkPath === '/' || str_contains($link['href'], 'website')) {
                                $isActive = $currentPath === '/' || str_contains($currentUrl, 'website');
                            } else {
                                $isActive = $currentPath === $linkPath;
                            }

                            $footerActiveClasses = $isActive
                                ? 'text-white font-semibold'
                                : 'text-gray-300 hover:text-white';
                        @endphp
                        <a href="{{ \Noerd\Cms\Support\SafeUrl::href($link['href'] ?? null) }}"
                           @if(($link['new_tab'] ?? false)) target="_blank" rel="noopener noreferrer" @endif
                           class="transition-colors duration-200 {{ $footerActiveClasses }}">
                            {{ $link['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Contact Info -->
            @if(!empty($globals['phone']))
                <div>
                    <h3 class="text-lg font-semibold mb-4">{{ __('Contact') }}</h3>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $globals['phone']) }}" class="text-gray-300 hover:text-white transition-colors duration-200">
                        {{ $globals['phone'] }}
                    </a>
                </div>
            @endif

            <!-- Contact Form -->
            <div>
                @livewire('website::contact-form')
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="border-t border-gray-700 mt-8 pt-6 text-center text-sm text-gray-400">
            <p>&copy; {{ now()->year }} {{ $globals['siteTitle'] ?? 'CMS Frontend' }}. {{ __('All rights reserved.') }}</p>
        </div>
    </div>
</footer>

@livewireScripts
@include('website::partials.cookie-consent', ['tracking' => $tracking])
</body>
</html>


