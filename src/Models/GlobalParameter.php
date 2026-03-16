<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;

class GlobalParameter extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory()
    {
        return \Noerd\Cms\Database\Factories\GlobalParameterFactory::new();
    }
}
