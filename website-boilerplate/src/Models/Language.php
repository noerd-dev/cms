<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $table = 'cms_languages';

    protected $guarded = [];

    protected static function newFactory()
    {
        return \Noerd\Website\Database\Factories\LanguageFactory::new();
    }
}
