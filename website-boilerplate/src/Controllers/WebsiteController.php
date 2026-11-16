<?php

declare(strict_types=1);

namespace Noerd\Website\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Cms\Support\PageMeta;
use Noerd\Cms\Support\SafeUrl;
use Noerd\Website\Models\Page;
use Noerd\Website\Models\Redirect;
use Noerd\Website\Services\PageElementService;

/**
 * The public page routes. Every lookup is bound to the tenant the
 * WebsiteMiddleware resolved and to ACTIVE pages: the read models carry no
 * tenant scope, so each query names the tenant itself.
 */
class WebsiteController extends Controller
{
    public function index(): View
    {
        $tenantId = (int) request()->attributes->get('tenant_id');
        $homepageId = CmsSetting::forTenant($tenantId)->homepage_page_id;

        $page = $homepageId
            ? Page::with(['elements'])
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->find($homepageId)
            : null;

        if (! $page) {
            abort(404, 'Homepage not configured');
        }

        return $this->render($page);
    }

    public function slug(
        ?string $slug1,
        ?string $slug2 = null,
        ?string $slug3 = null,
    ): View|RedirectResponse {
        $slug = '/' . $slug1;
        if ($slug2) {
            $slug .= '/' . $slug2;
        }
        if ($slug3) {
            $slug .= '/' . $slug3;
        }

        $tenantId = (int) request()->attributes->get('tenant_id');

        // The slug decides the language: look it up per configured language,
        // default language first — the set is tenant-configurable, never a
        // hardcoded list.
        $page = null;
        foreach (CmsLanguageCodes::active() as $languageCode) {
            // The code ends up in a JSON path.
            if (! preg_match('/^[a-z]{2,8}(-[a-z0-9]{2,8})?$/i', $languageCode)) {
                continue;
            }

            $page = Page::with(['elements'])
                ->whereJsonContains('slug->' . $languageCode, $slug)
                ->where('is_active', true)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($page) {
                session([\Noerd\Website\Support\WebsiteLanguage::SESSION_KEY => $languageCode]);

                break;
            }
        }

        if (! $page) {
            $targetUrl = $this->resolveRedirect($slug, $tenantId);
            if ($targetUrl !== null) {
                return redirect($targetUrl, 301);
            }

            abort(404, 'Page not found');
        }

        return $this->render($page);
    }

    private function render(Page $page): View
    {
        $language = (string) \Noerd\Website\Support\WebsiteLanguage::current();

        return view('website::page', [
            'page' => $page,
            'elements' => app(PageElementService::class)->processPageElements($page, $language),
            'meta' => PageMeta::of($page, $language),
        ]);
    }

    /**
     * A managed redirect only ever answers a path no active page claims, so it
     * can never shadow live content — and it only ever points at a path of
     * this site, never at another host (a stored `//evil.com` is ignored).
     */
    private function resolveRedirect(string $slug, int $tenantId): ?string
    {
        $redirect = Redirect::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('source_path', Redirect::normalizePath($slug))
            ->first();

        if (! $redirect) {
            return null;
        }

        $target = Page::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($redirect->target_page_id);

        if (! $target) {
            return null;
        }

        $slugs = is_array($target->slug) ? $target->slug : [];
        $language = \Noerd\Website\Support\WebsiteLanguage::current() ?? CmsLanguageCodes::active()[0];
        $url = $slugs[$language] ?? (reset($slugs) ?: null);

        if (! SafeUrl::isSitePath($url)) {
            return null;
        }

        // Redirecting a path onto itself would loop forever.
        return $url !== $slug ? $url : null;
    }
}
