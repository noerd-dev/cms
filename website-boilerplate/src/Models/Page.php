<?php

declare(strict_types=1);

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Concerns\ExtendsContent;
use Noerd\Website\Database\Factories\PageFactory;

/**
 * Read model of a website page. The name and the elements live on the page's
 * content (`contents` / `content_elements`), reached through ExtendsContent.
 */
class Page extends Model
{
    use ExtendsContent;
    use HasFactory;

    protected $table = 'cms_pages';

    protected $guarded = [];

    protected $casts = [
        'slug' => 'json',
        'is_active' => 'boolean',
    ];

    public static function contentType(): string
    {
        return 'cms.page';
    }

    protected static function newFactory()
    {
        return PageFactory::new();
    }
}
