<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Cms\Database\Factories\RedirectFactory;
use Noerd\Traits\BelongsToTenant;

class Redirect extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'cms_redirects';

    /**
     * Bring an incoming path into the single shape stored in `source_path`, so
     * an editor's `Agentur/` and a visitor's `/agentur?utm=x` resolve to the
     * same row. The website module carries an identical copy — keep both in sync.
     */
    public static function normalizePath(string $path): string
    {
        $path = preg_split('/[?#]/', mb_trim($path))[0] ?? '';
        $path = mb_trim(mb_strtolower(mb_trim($path)), '/');

        return $path === '' ? '/' : '/' . $path;
    }

    public function targetPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'target_page_id');
    }

    protected static function newFactory(): RedirectFactory
    {
        return RedirectFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function sourcePath(): Attribute
    {
        return Attribute::make(
            set: fn(string $value): string => self::normalizePath($value),
        );
    }
}
