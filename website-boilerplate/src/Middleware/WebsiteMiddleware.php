<?php

namespace Noerd\Website\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Noerd\Website\Models\Language;
use Noerd\Website\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class WebsiteMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;
        $tenantId = null;

        // 1. Try uuid-based tenant resolution first
        $uuid = $request->uuid ?? session('uuid');
        if (! empty($uuid)) {
            $tenant = Tenant::where('uuid', $uuid)->first();
            if ($tenant) {
                $tenantId = $tenant->id;
                session(['uuid' => $uuid]);
            }
        }

        // 1b. The backend quick-menu links to the website with ?uuid={tenant}
        if (! $tenant && ! empty($request->uuid)) {
            $tenant = Tenant::where('uuid', $request->uuid)->first();
            if ($tenant) {
                $tenantId = $tenant->id;
            }
        }

        // 2. Fallback to first available tenant if no uuid or tenant found
        if (! $tenant) {
            $tenant = Tenant::first();
            if (! $tenant) {
                abort(404, 'No tenant available.');
            }
            $tenantId = $tenant->id;
        }

        // Handle language parameter
        if ($request->has('language')) {
            $requestedLanguage = $request->get('language');

            // Check if the requested language exists and is active for this tenant
            $language = Language::where('tenant_id', $tenantId)
                ->where('code', $requestedLanguage)
                ->where('is_active', true)
                ->first();

            if ($language) {
                session(['selectedLanguage' => $requestedLanguage]);
            }
        }

        // Set Laravel's locale based on selected language
        $selectedLanguage = session('selectedLanguage');
        if (! $selectedLanguage) {
            $defaultLanguage = Language::where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
            $selectedLanguage = $defaultLanguage ? $defaultLanguage->code : 'en';
            session(['selectedLanguage' => $selectedLanguage]);
        }

        // Set Laravel's application locale for translations
        app()->setLocale($selectedLanguage);

        View::share('tenant', $tenant);
        session(['selectedTenantId' => $tenantId]);

        // Attach to request for downstream usage
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
