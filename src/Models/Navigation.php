<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\NavigationFactory;
use Noerd\Traits\BelongsToTenant;

class Navigation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $casts = [
        'new_tab' => 'boolean',
    ];

    protected $guarded = [];

    protected $table = 'cms_navigations';

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    protected static function newFactory()
    {
        return NavigationFactory::new();
    }
}
