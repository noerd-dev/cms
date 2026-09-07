<?php

declare(strict_types=1);

namespace Noerd\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class CmsApiAuth
{
    /**
     * Handle an incoming request.
     *
     * Expects an API token of a user in either:
     * - Authorization: Bearer <token>
     * - X-API-Key: <token>
     *
     * Deliberately NOT in the query string: query parameters end up in access
     * logs, browser history and Referer headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = null;

        $authHeader = $request->header('Authorization');
        if (is_string($authHeader) && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = mb_trim($m[1]);
        }
        if (! $token) {
            $token = (string) $request->header('X-API-Key', '');
        }

        // One generic message for every failure mode: the response must not
        // disclose whether a presented token exists or how far it got.
        if (! $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = NoerdUser::where('api_token', $token)->first();
        if (! $user || ! $user->selected_tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tenant = Tenant::find($user->selected_tenant_id);
        if (! $tenant) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // A token only unlocks the CMS API for a tenant that runs the CMS app.
        if (! $tenant->tenantApps()->where('name', 'CMS')->exists()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Attach user and tenant context to request
        $request->attributes->set('tenant_id', $tenant->id);
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('user', $user);

        return $next($request);
    }
}
