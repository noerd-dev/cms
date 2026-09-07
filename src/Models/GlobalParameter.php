<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Database\Factories\GlobalParameterFactory;
use Noerd\Traits\BelongsToTenant;

class GlobalParameter extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'cms_global_parameters';

    protected static function newFactory(): GlobalParameterFactory
    {
        return GlobalParameterFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_translatable' => 'boolean',
        ];
    }
}
