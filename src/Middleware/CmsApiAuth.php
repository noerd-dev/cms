<?php

namespace Noerd\Cms\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noerd\Models\Tenant;
use Noerd\Models\NoerdUser;
use Symfony\Component\HttpFoundation\Response;

class CmsApiAuth
{
    /**
     * Handle an incoming request.
     *
     * Expects an API token of a user in either:
     * - Authorization: Bearer <token>
     * - X-API-Key: <token>
     * - query parameter api_token
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
        if (! $token) {
            $token = (string) $request->query('api_token', '');
        }

        if (! $token) {
            return response()->json(['message' => 'Unauthorized: missing API token'], 401);
        }

        $user = NoerdUser::where('api_token', $token)->first();
        if (! $user || ! $user->selected_tenant_id) {
            return response()->json(['message' => 'Unauthorized: invalid API token'], 401);
        }

        $tenant = Tenant::find($user->selected_tenant_id);
        if (! $tenant) {
            return response()->json(['message' => 'Unauthorized: tenant not found'], 401);
        }

        // Attach user and tenant context to request
        $request->attributes->set('tenant_id', $tenant->id);
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('user', $user);

        return $next($request);
    }
}
