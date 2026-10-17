<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Database\Factories\ContentFactory;
use Noerd\Cms\Support\ContentTypeRegistry;
use Noerd\Traits\BelongsToTenant;

/**
 * The shared base of every page-like record: a name and the elements. What only
 * one kind of content needs lives in its extension table (cms_pages, or a
 * table of the module that adds the type), which points here through
 * content_id and uses the ExtendsContent concern. `type` is the key of the ContentTypeRegistry.
 *
 * Object permissions are checked on the EXTENSION model (a page, an article),
 * never on the content: a content is never edited on its own.
 */
class Content extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'contents';

    public function elements(): HasMany
    {
        return $this->hasMany(ContentElement::class)->orderBy('sort');
    }

    /**
     * The extension record of this content (a cms Page, a record of another module, …), or
     * null when the type is not registered or the record is gone.
     */
    public function extension(): ?Model
    {
        $type = app(ContentTypeRegistry::class)->forType((string) $this->type);

        if (! $type) {
            return null;
        }

        $query = $type->modelClass::query()->withoutGlobalScopes();

        return $query->where('content_id', $this->getKey())->first();
    }

    /**
     * The name in the given language, falling back to the first filled one.
     */
    public function localizedName(?string $language = null): string
    {
        $names = is_array($this->name) ? $this->name : [];

        if ($language !== null && is_string($names[$language] ?? null) && $names[$language] !== '') {
            return $names[$language];
        }

        foreach ($names as $name) {
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return '';
    }

    protected static function newFactory(): ContentFactory
    {
        return ContentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'custom_attributes' => 'array',
        ];
    }
}
