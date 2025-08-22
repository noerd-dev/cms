<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Navigation extends Model
{
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
}
