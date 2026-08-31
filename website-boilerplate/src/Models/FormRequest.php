<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;

class FormRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'form_requests';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    protected static function newFactory()
    {
        return \Noerd\Website\Database\Factories\FormRequestFactory::new();
    }
}
