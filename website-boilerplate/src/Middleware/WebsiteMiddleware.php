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
        $hash = $request->hash ?? session('hash');
        if (empty($hash)) {
            abort(400, 'Missing required hash parameter.');
        }

        $tenant = Tenant::where('hash', $hash)->first();
        if (!$tenant) {
            abort(404, 'Tenant not found.');
        }

        $globals = GlobalParameter::where('tenant_id', $tenant->id)->get()
            ->mapWithKeys(function ($item) {
                $decoded = json_decode($item->value, true);
                return [$item->key => $decoded];
            });

        View::share('globals', $globals);
        View::share('tenant', $tenant);
        session(['hash' => $hash]);
        session(['selectedTenantId' => $tenant->id]);

        // Optionally attach to request for downstream usage
        $request->attributes->set('tenant_id', $tenant->id);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
