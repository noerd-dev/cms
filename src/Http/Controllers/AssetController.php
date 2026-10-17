<?php

declare(strict_types=1);

namespace Noerd\Cms\Http\Controllers;

use Illuminate\Http\Request;
use Noerd\Cms\Support\AssetManifest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the prebuilt bundle straight from the package directory — the same
 * approach Livewire takes for livewire.js. Only files listed in the Vite
 * manifest are served, so the route can never reach outside dist/build.
 */
final class AssetController
{
    private const int ONE_YEAR = 31536000;

    public function __invoke(Request $request, string $file): Response
    {
        $path = AssetManifest::path($file);

        if ($path === null || ! is_file($path)) {
            abort(404);
        }

        $lastModified = (int) filemtime($path);

        $headers = [
            'Content-Type' => $this->contentType($file),
            // The hash in the file name changes with every build, so the
            // response may be cached without revalidation.
            'Cache-Control' => 'public, max-age=' . self::ONE_YEAR . ', immutable',
            'Expires' => gmdate('D, d M Y H:i:s', time() + self::ONE_YEAR) . ' GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
        ];

        if ($this->matchesCache($request, $lastModified)) {
            return response('', 304, [
                'Cache-Control' => $headers['Cache-Control'],
                'Expires' => $headers['Expires'],
            ]);
        }

        return new BinaryFileResponse($path, 200, $headers, false);
    }

    private function contentType(string $file): string
    {
        return str_ends_with($file, '.css')
            ? 'text/css; charset=utf-8'
            : 'application/javascript; charset=utf-8';
    }

    private function matchesCache(Request $request, int $lastModified): bool
    {
        $ifModifiedSince = $request->headers->get('If-Modified-Since');

        return $ifModifiedSince !== null && @strtotime($ifModifiedSince) === $lastModified;
    }
}
