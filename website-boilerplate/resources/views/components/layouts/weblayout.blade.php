@props(['title' => 'CMS Frontend'])

@php
    // Navigation active state logic - inline to avoid function redeclaration errors
    $currentUrl = request()->url();
    $currentPath = parse_url($currentUrl, PHP_URL_PATH);
@endphp

    <!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col bg-gray-50 text-gray-900">

<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center">
                <a href="{{ route('website.index', ['hash' => request('hash')]) }}" class="flex items-center">
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
                            <a href="{{ $link['href'] }}"
                               @if(($link['new_tab'] ?? false)) target="_blank" rel="noopener" @endif
                               class="nav-link {{ $isActive ? 'nav-link--active' : 'nav-link--inactive' }}">
                                {{ $link['name'] }}
                            </a>
                        @empty
                            <a href="#" class="nav-link nav-link--inactive">Home</a>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="hidden md:flex items-center space-x-4">
                <livewire:frontend-language-switcher/>
            </div>

            <div class="md:hidden">
                <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-black" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">Menü öffnen</span>
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
                <a href="{{ $link['href'] }}"
                   @if(($link['new_tab'] ?? false)) target="_blank" rel="noopener" @endif
                   class="nav-link block text-base {{ $isActive ? 'nav-link--active' : 'nav-link--inactive' }}">
                    {{ $link['name'] }}
                </a>
            @endforeach
            <div class="border-t border-gray-200 my-2"></div>
            <div class="px-3 py-2">
                @livewire('frontend-language-switcher', key('language-switcher-mobile'))
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
                        <a href="{{ $link['href'] }}"
                           class="transition-colors duration-200 {{ $footerActiveClasses }}">
                            {{ $link['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Contact Info -->
            @if(!empty($globals['phone']))
                <div>
                    <h3 class="text-lg font-semibold mb-4">{{ __('Kontakt') }}</h3>
                    <a href="tel:{{ $globals['phone'] }}" class="text-gray-300 hover:text-white transition-colors duration-200">
                        {{ $globals['phone'] }}
                    </a>
                </div>
            @endif

            <!-- Contact Form -->
            <div>
                @livewire('contact-form')
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="border-t border-gray-700 mt-8 pt-6 text-center text-sm text-gray-400">
            <p>&copy; {{ date('Y') }} {{ $globals['siteTitle'] ?? 'CMS Frontend' }}. {{ __('Alle Rechte vorbehalten.') }}</p>
        </div>
    </div>
</footer>

@livewireScripts
</body>
</html>


