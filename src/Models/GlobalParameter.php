<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Noerd\Traits\BelongsToTenant;
use Noerd\Noerd\Traits\HasListScopes;

class GlobalParameter extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasListScopes;

    protected $guarded = [];

    protected array $searchable = [
        'key',
        'value',
    ];

    protected static function newFactory()
    {
        return \Noerd\Cms\Database\Factories\GlobalParameterFactory::new();
    }
}
