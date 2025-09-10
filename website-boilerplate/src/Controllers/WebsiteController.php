<?php

namespace Noerd\Website\Controllers;

use App\Http\Controllers\Controller;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Website\Models\Page;
use Noerd\Website\Services\PageElementService;

class WebsiteController extends Controller
{
    public function __construct()
    {
        if (! session()->has('selectedLanguage')) {
            session(['selectedLanguage' => 'de']);
        }
    }

    public function index()
    {
        // Find a page with elements for demo/testing
        $cmsSettings = CmsSetting::where('tenant_id', request()->attributes->get('tenant_id'))->first();

        $page = Page::with(['elements'])->whereHas('elements')->find($cmsSettings->homepage_page_id);

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
        $page = Page::with(['elements', 'collections.rows'])->find($pageId);

        $elements = [];
        $pageElements = $page->elements;
        foreach ($pageElements as $pageElement) {
            $elementKey = $pageElement->element_key ?? $pageElement->element?->element_key ?? null;

            if (empty($elementKey)) {
                continue;
            }

            $element['key'] = $elementKey;
            $element['data'] = (object) $this->localizeElementData(json_decode($pageElement->data, true));
            $elements[] = $element;
        }

        $selectedLanguage = session('selectedLanguage', 'de');

        return view('page', [
            'page' => $page,
            'elements' => $elements,
        ]);
    }

    public function slug(
        ?string $slug1,
        ?string $slug2 = null,
        ?string $slug3 = null,
        ?string $slug4 = null,
        ?string $slug5 = null,
    ) {
        $slug = '/'.$slug1;
        if ($slug2) {
            $slug .= '/'.$slug2;
        }
        if ($slug3) {
            $slug .= '/'.$slug3;
        }
        if ($slug4) {
            $slug .= '/'.$slug4;
        }
        if ($slug5) {
            $slug .= '/'.$slug5;
        }

        $tenantId = request()->attributes->get('tenant_id');

        $page = Page::with(['elements'])
            ->whereJsonContains('slug->de', $slug)
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->first();
        session(['selectedLanguage' => 'de']);

        if (! $page) {
            $page = Page::with(['elements'])
                ->whereJsonContains('slug->en', $slug)
                ->where('is_active', 1)
                ->where('tenant_id', $tenantId)
                ->first();
            session(['selectedLanguage' => 'en']);
        }

        if (! $page) {
            // Home Page
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
     * Localize element data based on selected language
     */
    private function localizeElementData(?array $data, string $selectedLanguage = 'de'): array
    {
        if (! $data) {
            return [];
        }

        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value[$selectedLanguage])) {
                $data[$key] = $value[$selectedLanguage];
            }
        }

        return $data;
    }
}
