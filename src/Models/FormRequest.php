<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\FormRequestFactory;

class FormRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'form_requests';

    protected static function newFactory()
    {
        return FormRequestFactory::new();
    }
}
