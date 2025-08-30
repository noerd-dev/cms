<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Navigation extends Model
{
    use HasFactory;

    protected $table = 'cms_navigations';

    protected $guarded = [];

    protected static function newFactory()
    {
        return \Noerd\Website\Database\Factories\NavigationFactory::new();
    }
}
