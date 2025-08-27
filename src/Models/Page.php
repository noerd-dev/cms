<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\PageFactory;

class Page extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'data' => 'array',
        'name' => 'array',
        'slug' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function elements()
    {
        return $this->hasMany(ElementPage::class)->orderBy('sort');
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    protected static function newFactory()
    {
        return PageFactory::new();
    }
}
