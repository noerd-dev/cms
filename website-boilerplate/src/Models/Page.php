<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Website\Database\Factories\PageFactory;

class Page extends Model
{
    use HasFactory;

    protected $table = 'pages';

    protected $guarded = [];

    protected $casts = [
        'name' => 'json',
        'slug' => 'json',
        'is_active' => 'boolean',
    ];

    public function elements()
    {
        return $this->hasMany(ElementPage::class)->with('element')->orderBy('sort');
    }

    protected static function newFactory()
    {
        return PageFactory::new();
    }
}
