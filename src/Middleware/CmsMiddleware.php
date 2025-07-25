<?php

namespace Noerd\Cms\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        session(['currentApp' => 'CMS']);
        if (!session('selectedLanguage')) {
            session(['selectedLanguage' => 'de']);
        }

        $activeApp = auth()->user()->selectedTenant()->tenantApps()->where('name', 'CMS')->count();
        if ($activeApp === 0) {
            return redirect('/');
        }

        return $next($request);
    }
}
