<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    public $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $table = 'cms_languages';


    protected $guarded = [];
}
