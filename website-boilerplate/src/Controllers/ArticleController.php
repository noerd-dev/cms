<?php

declare(strict_types=1);

namespace Noerd\Website\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Cms\Support\PageMeta;

/**
 * The blog: published articles of the request's tenant (`Article::published()`
 * — active, with a publication date that has arrived).
 */
class ArticleController extends Controller
{
    public function index(): View
    {
        $tenantId = (int) request()->attributes->get('tenant_id');
        $language = $this->language();

        $articles = Article::published()
            ->where('tenant_id', $tenantId)
            ->with('author')
            ->orderByDesc('publication_date')
            ->paginate(12);

        foreach ($articles->items() as $article) {
            $article->display_title = $this->localized($article->title, $language);
            $article->display_slug = $this->localized($article->slug, $language);
            $article->featured_image_url = PageMeta::imageUrl($article->featured_image);
        }

        return view('website::articles', [
            'articles' => $articles,
        ]);
    }

    public function show(string $slug): View
    {
        $tenantId = (int) request()->attributes->get('tenant_id');
        $slug = '/' . $slug;

        // The slug decides the language — every active language of the
        // tenant, the default first.
        $article = null;
        $language = $this->language();
        foreach (CmsLanguageCodes::active() as $code) {
            if (! preg_match('/^[a-z]{2,8}(-[a-z0-9]{2,8})?$/i', $code)) {
                continue;
            }

            $article = Article::published()
                ->where('tenant_id', $tenantId)
                ->with('author')
                ->whereJsonContains('slug->' . $code, $slug)
                ->first();

            if ($article) {
                $language = $code;

                break;
            }
        }

        if (! $article) {
            abort(404);
        }

        session([\Noerd\Website\Support\WebsiteLanguage::SESSION_KEY => $language]);

        $article->display_title = $this->localized($article->title, $language);
        $article->featured_image_url = PageMeta::imageUrl($article->featured_image);

        return view('website::article', [
            'article' => $article,
        ]);
    }

    private function language(): string
    {
        return (string) \Noerd\Website\Support\WebsiteLanguage::current();
    }

    private function localized(mixed $value, string $language): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        return (string) ($value[$language] ?? (array_values($value)[0] ?? ''));
    }
}
