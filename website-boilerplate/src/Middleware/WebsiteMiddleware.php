<?php

declare(strict_types=1);

namespace Noerd\Website\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Support\TenantContext;
use Noerd\Website\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant of a website request and its language.
 *
 * The tenant comes from `?uuid=` (the backend's quick-menu link), the uuid
 * remembered in the session, or — for a single-tenant installation — the
 * first tenant. The uuid is read from the QUERY STRING only, never from a
 * request body: a form post must not switch the tenant a session works in.
 *
 * Residual risk (documented, by design): whoever knows a tenant's uuid sees
 * that tenant's ACTIVE pages on any host serving this website. Pages are
 * public content, but an installation that serves several tenants on their own
 * domains should bind the tenant to the host instead of the uuid.
 */
class WebsiteMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        $requestUuid = $request->query('uuid');
        $uuid = is_string($requestUuid) && $requestUuid !== '' ? $requestUuid : session('uuid');

        if (is_string($uuid) && $uuid !== '') {
            $tenant = Tenant::where('uuid', $uuid)->first();

            if ($tenant) {
                session(['uuid' => $uuid]);
            }
        }

        // Fallback to the first tenant (single-tenant installations).
        if (! $tenant) {
            $tenant = Tenant::query()->orderBy('id')->first();

            if (! $tenant) {
                abort(404, 'No tenant available.');
            }
        }

        $tenantId = (int) $tenant->id;

        // A visitor has no noerd user: every tenant-scoped query of the website
        // works in the resolved tenant (registered as persistent middleware, so
        // Livewire updates of the contact form get it as well). Set BEFORE the
        // language lookups below, which read the tenant's languages.
        TenantContext::set($tenantId);

        $activeLanguages = CmsLanguageCodes::active();

        // ?language= switches to an active language of this tenant only.
        $requestedLanguage = $request->query('language');
        if (is_string($requestedLanguage) && in_array($requestedLanguage, $activeLanguages, true)) {
            session([\Noerd\Website\Support\WebsiteLanguage::SESSION_KEY => $requestedLanguage]);
        }

        // A language of another tenant (the session outlived a tenant switch)
        // or none at all falls back to the tenant's default language.
        if (! in_array(\Noerd\Website\Support\WebsiteLanguage::current(), $activeLanguages, true)) {
            session([\Noerd\Website\Support\WebsiteLanguage::SESSION_KEY => $activeLanguages[0]]);
        }

        app()->setLocale((string) \Noerd\Website\Support\WebsiteLanguage::current());

        View::share('tenant', $tenant);
        session(['selectedTenantId' => $tenantId]);

        // Attach to request for downstream usage
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
