<x-website::layouts.weblayout>
    <header class="bg-gradient-to-b from-white to-gray-50">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">Willkommen</h1>
            <p class="mt-4 max-w-2xl text-gray-600">
                Ein simples Tailwind/Livewire-Boilerplate als Startpunkt für dein CMS‑Frontend.
            </p>
            <div class="mt-6 flex gap-3">
                <a
                    href="#features"
                    class="inline-flex items-center rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                >Mehr erfahren</a>
                <a
                    href="#contact"
                    class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-100"
                >Kontakt aufnehmen</a>
            </div>
        </div>
    </header>

    <main class="flex-1">
        <section id="features" class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <h2 class="text-xl font-semibold">Features</h2>
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="font-medium">Tailwind CSS</div>
                    <p class="mt-2 text-sm text-gray-600">Schnelles Styling mit Utility‑Klassen.</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="font-medium">Livewire</div>
                    <p class="mt-2 text-sm text-gray-600">Interaktive Komponenten ohne viel JavaScript.</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="font-medium">Responsive</div>
                    <p class="mt-2 text-sm text-gray-600">Optimiert für Mobil‑, Tablet‑ und Desktop‑Geräte.</p>
                </div>
            </div>
        </section>

        <section id="contact" class="border-t border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="text-xl font-semibold">Kontakt</h2>
                <p class="mt-2 text-gray-600">Platzhalter für deine Kontaktinformationen oder ein Formular.</p>
            </div>
        </section>
    </main>
</x-website::layouts.weblayout>
