<?php

declare(strict_types=1);

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Website\Database\Factories\PageFactory;

class Page extends Model
{
    use HasFactory;

    protected $table = 'cms_pages';

    protected $guarded = [];

    protected $casts = [
        'name' => 'json',
        'slug' => 'json',
        'is_active' => 'boolean',
    ];

    public function elements()
    {
        return $this->hasMany(ElementPage::class)->orderBy('sort');
    }

    protected static function newFactory()
    {
        return PageFactory::new();
    }
}
