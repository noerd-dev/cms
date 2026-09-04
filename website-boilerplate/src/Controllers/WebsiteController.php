<?php

namespace Noerd\Website\Controllers;

use App\Http\Controllers\Controller;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Website\Models\Page;
use Noerd\Website\Models\Redirect;
use Noerd\Website\Services\PageElementService;

class WebsiteController extends Controller
{
    public function __construct()
    {
        if (! session()->has('selectedLanguage')) {
            session(['selectedLanguage' => CmsLanguageCodes::active()[0] ?? 'de']);
        }
    }

    public function index()
    {
        $cmsSettings = CmsSetting::where('tenant_id', request()->attributes->get('tenant_id'))->first();

        $page = $cmsSettings?->homepage_page_id
            ? Page::with(['elements'])->find($cmsSettings->homepage_page_id)
            : null;

        if (! $page) {
            abort(404, 'Homepage not configured');
        }

        $pageElementService = app(PageElementService::class);
        $selectedLanguage = session('selectedLanguage', 'de');
        $elements = $pageElementService->processPageElements($page, $selectedLanguage);

        return view('website::page', [
            'page' => $page,
            'elements' => $elements,
        ]);
    }

    public function page(int $pageId)
    {
        $page = Page::with(['elements'])->find($pageId);

        $pageElementService = app(PageElementService::class);
        $selectedLanguage = session('selectedLanguage', 'de');
        $elements = $pageElementService->processPageElements($page, $selectedLanguage);

        return view('website::page', [
            'page' => $page,
            'elements' => $elements,
        ]);
    }

    public function slug(
        ?string $slug1,
        ?string $slug2 = null,
        ?string $slug3 = null,
    ) {
        $slug = '/' . $slug1;
        if ($slug2) {
            $slug .= '/' . $slug2;
        }
        if ($slug3) {
            $slug .= '/' . $slug3;
        }

        $tenantId = request()->attributes->get('tenant_id');

        // The slug decides the language: look it up per configured language,
        // default language first — the set is tenant-configurable, never a
        // hardcoded list.
        $page = null;
        foreach (CmsLanguageCodes::active() as $languageCode) {
            $page = Page::with(['elements'])
                ->whereJsonContains('slug->' . $languageCode, $slug)
                ->where('is_active', 1)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($page) {
                session(['selectedLanguage' => $languageCode]);

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

        $pageElementService = app(PageElementService::class);
        $selectedLanguage = session('selectedLanguage', 'de');
        $elements = $pageElementService->processPageElements($page, $selectedLanguage);

        return view('website::page', [
            'page' => $page,
            'elements' => $elements,
        ]);
    }

    /**
     * A managed redirect only ever answers a path no active page claims, so it
     * can never shadow live content.
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
            ->where('is_active', 1)
            ->find($redirect->target_page_id);

        if (! $target) {
            return null;
        }

        $slugs = (array) ($target->slug ?? []);
        $language = session('selectedLanguage') ?? (CmsLanguageCodes::active()[0] ?? 'de');
        $url = $slugs[$language] ?? (reset($slugs) ?: null);

        // Redirecting a path onto itself would loop forever.
        return is_string($url) && $url !== $slug ? $url : null;
    }
}
