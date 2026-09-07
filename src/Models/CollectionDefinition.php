<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Cms\Database\Factories\CollectionDefinitionFactory;
use Noerd\Models\NoerdUser;
use Noerd\Traits\BelongsToTenant;

class CollectionDefinition extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'cms_collection_definitions';

    protected $guarded = [];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'created_by');
    }

    protected static function newFactory(): CollectionDefinitionFactory
    {
        return CollectionDefinitionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'has_page' => 'boolean',
        ];
    }

    protected function key(): Attribute
    {
        return Attribute::make(
            set: fn(string $value) => mb_strtoupper($value),
        );
    }

    protected function filename(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                $value = mb_strtolower($value);
                $value = str_replace('_', '-', $value);

                return preg_replace('/[^a-z0-9\-]/', '', $value);
            },
        );
    }
}
