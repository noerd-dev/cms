<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Cms\Database\Factories\CollectionDefinitionFactory;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

class CollectionDefinition extends Model
{
    use HasFactory;

    protected $table = 'collection_definitions';

    protected $guarded = [];

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
            set: fn (string $value) => mb_strtoupper($value),
        );
    }

    protected function filename(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                $value = mb_strtolower($value);
                $value = preg_replace('/\.ya?ml$/i', '', $value);
                $value = str_replace('_', '-', $value);

                return preg_replace('/[^a-z0-9\-]/', '', $value);
            },
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'created_by');
    }

    protected static function newFactory()
    {
        return CollectionDefinitionFactory::new();
    }
}
