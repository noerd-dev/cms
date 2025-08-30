<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $guarded = [];

    protected $casts = [
        // Add casts as needed based on actual table schema
    ];

    protected static function newFactory()
    {
        return \Noerd\Website\Database\Factories\TenantFactory::new();
    }
}
