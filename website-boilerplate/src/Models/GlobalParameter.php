<?php

declare(strict_types=1);

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalParameter extends Model
{
    protected $table = 'cms_global_parameters';

    protected $guarded = [];
}
