<x-website::layouts.weblayout :title="$article->display_title" :meta="['title' => $article->display_title, 'image' => $article->featured_image_url]">
    <div class="pt-48">
        <div class="mx-auto max-w-3xl px-6 pb-8">
            <h1 class="mb-4 text-4xl font-bold">{{ $article->display_title }}</h1>

            <div class="mb-8 flex items-center gap-2 text-sm text-gray-500">
                @if ($article->author)
                    <span>{{ $article->author->name }}</span>
                    <span>&middot;</span>
                @endif
                <span>{{ \Noerd\Helpers\FormatHelper::date($article->publication_date, \Noerd\Helpers\FormatHelper::tenantLocale((int) $article->tenant_id)) }}</span>
            </div>

            @if ($article->featured_image_url)
                <img src="{{ $article->featured_image_url }}" alt="{{ $article->display_title }}" class="mb-8 w-full rounded-lg">
            @endif

            @if ($article->body)
                {{-- The body is rich text (HTML from the editor): printed through the allow-list sanitizer. --}}
                <div class="prose max-w-none">{!! \Noerd\Support\HtmlSanitizer::sanitize($article->body) !!}</div>
            @endif

            <div class="mt-12">
                <a href="{{ route('website.articles') }}" class="text-gray-600 transition-colors hover:text-gray-900">
                    &larr; {{ __('Back to Blog') }}
                </a>
            </div>
        </div>
    </div>
</x-website::layouts.weblayout>
