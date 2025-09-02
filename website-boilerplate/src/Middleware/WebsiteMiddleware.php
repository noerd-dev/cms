<?php

namespace Noerd\Website\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Noerd\Website\Models\GlobalParameter;
use Noerd\Website\Models\Tenant;

class WebsiteMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;
        $tenantId = null;

        // 1. Try hash-based tenant resolution first
        $hash = $request->hash ?? session('hash');
        if (!empty($hash)) {
            $tenant = Tenant::where('hash', $hash)->first();
            if ($tenant) {
                $tenantId = $tenant->id;
                session(['hash' => $hash]);
            }
        }

        // 2. Fallback to first available tenant if no hash or tenant found
        if (!$tenant) {
            $tenant = Tenant::first();
            if (!$tenant) {
                abort(404, 'No tenant available.');
            }
            $tenantId = $tenant->id;
        }

        $globals = GlobalParameter::where('tenant_id', $tenantId)->get()
            ->mapWithKeys(function ($item) {
                $decoded = json_decode($item->value, true);
                return [$item->key => $decoded];
            });

        View::share('globals', $globals);
        View::share('tenant', $tenant);
        session(['selectedTenantId' => $tenantId]);

        // Attach to request for downstream usage
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
