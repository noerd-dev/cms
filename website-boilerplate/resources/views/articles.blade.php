<x-website::layouts.weblayout :title="__('Blog')">
    <div class="pt-48">
        <div class="mx-auto max-w-7xl px-6 pb-8">
            <h1 class="mb-8 text-3xl font-bold">{{ __('Blog') }}</h1>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <a
                        href="{{ route('website.article', ['slug' => ltrim($article->display_slug, '/')]) }}"
                        class="block overflow-hidden rounded-lg bg-white shadow-sm transition-shadow duration-200 hover:shadow-md"
                    >
                        @if ($article->featured_image_url)
                            <img src="{{ $article->featured_image_url }}" alt="{{ $article->display_title }}" class="aspect-video w-full object-cover">
                        @endif
                        <div class="p-6">
                            <h2 class="mb-2 text-xl font-semibold">{{ $article->display_title }}</h2>
                            <div class="mb-3 flex items-center gap-2 text-sm text-gray-500">
                                @if ($article->author)
                                    <span>{{ $article->author->name }}</span>
                                    <span>&middot;</span>
                                @endif
                                <span>{{ \Noerd\Helpers\FormatHelper::date($article->publication_date, \Noerd\Helpers\FormatHelper::tenantLocale((int) $article->tenant_id)) }}</span>
                            </div>
                            @if ($article->body)
                                <p class="line-clamp-3 text-gray-600">
                                    {{ Str::limit(strip_tags($article->body), 150) }}
                                </p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">{{ $articles->links() }}</div>
        </div>
    </div>
</x-website::layouts.weblayout>
