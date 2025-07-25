<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\TextFactory;
use Noerd\Noerd\Models\Tenant;

class Text extends Model
{
    use HasFactory;

    protected $primaryKey = 'tenant_id';

    protected $guarded = ['tenant_id'];

    public function client()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    protected static function newFactory(): TextFactory
    {
        return TextFactory::new();
    }
}
