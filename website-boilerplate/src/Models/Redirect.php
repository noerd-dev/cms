<?php

declare(strict_types=1);

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $table = 'cms_redirects';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Bring an incoming request path into the shape stored in `source_path`.
     * Mirrors Noerd\Cms\Models\Redirect::normalizePath() — the two must stay
     * in sync.
     */
    public static function normalizePath(string $path): string
    {
        $path = preg_split('/[?#]/', mb_trim($path))[0] ?? '';
        $path = mb_trim(mb_strtolower(mb_trim($path)), '/');

        return $path === '' ? '/' : '/' . $path;
    }
}
