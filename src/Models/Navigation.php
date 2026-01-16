<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Noerd\Traits\BelongsToTenant;
use Noerd\Noerd\Traits\HasListScopes;

class Navigation extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasListScopes;

    public $casts = [
        'new_tab' => 'boolean',
    ];

    protected $guarded = [];

    protected $table = 'cms_navigations';

    protected array $searchable = [
        'navigation_key',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }
}
