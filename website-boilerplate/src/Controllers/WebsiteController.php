<?php

namespace Noerd\Website\Controllers;

use App\Http\Controllers\Controller;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Website\Models\Page;
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
}
