<?php

declare(strict_types=1);

namespace Noerd\Cms\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Services\ContentElementEditorService;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Helpers\TenantHelper;
use Noerd\Services\ColumnFilterParser;

/**
 * Makes a model an EXTENSION of a content: the model's table carries what only
 * its kind needs (a page's slug, an article's issue) plus `content_id`, the
 * content carries the name and the elements.
 *
 * The content is created with the record, its attributes (`name` by default)
 * read and write like the model's own, and it is deleted with the record — a
 * soft-deleting model keeps it until the force delete. The extension table
 * needs `tenant_id` and `content_id`.
 *
 * @mixin Model
 */
trait ExtendsContent
{
    /**
     * Values of the content's attributes set on this model, written to the
     * content when the model is saved.
     *
     * @var array<string, mixed>
     */
    protected array $pendingContentAttributes = [];

    /**
     * The content a replicated record was copied from — its elements are
     * copied onto the replica's own content once the replica exists.
     */
    protected ?int $replicatedFromContentId = null;

    /**
     * The registry key of this kind of content (`cms.page`, `blog.post`, …).
     */
    abstract public static function contentType(): string;

    /**
     * The attributes that live on the content instead of the model's table.
     *
     * @return array<int, string>
     */
    public static function contentAttributes(): array
    {
        return ['name'];
    }

    public static function bootExtendsContent(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttributeFromArray('content_id')) {
                return;
            }

            $content = Content::query()->withoutGlobalScopes()->create([
                'tenant_id' => $model->getAttributeFromArray('tenant_id') ?? TenantHelper::currentTenantId(),
                'type' => static::contentType(),
                ...$model->pendingContentAttributes,
            ]);

            $model->setAttribute('content_id', $content->getKey());
            $model->setRelation('content', $content);
            $model->pendingContentAttributes = [];
        });

        // A replica never shares the original's content: it gets its own, with
        // the same name (unless set) and copies of the elements.
        static::replicating(function (Model $replica): void {
            $sourceId = $replica->getAttributeFromArray('content_id');

            if (! $sourceId) {
                return;
            }

            $source = Content::query()->withoutGlobalScopes()->find($sourceId);

            foreach (static::contentAttributes() as $key) {
                if (! array_key_exists($key, $replica->pendingContentAttributes)) {
                    $replica->pendingContentAttributes[$key] = $source?->getAttribute($key);
                }
            }

            $replica->offsetUnset('content_id');
            $replica->unsetRelation('content');
            $replica->replicatedFromContentId = (int) $sourceId;
        });

        static::created(function (Model $model): void {
            if ($model->replicatedFromContentId === null) {
                return;
            }

            $source = Content::query()->withoutGlobalScopes()->find($model->replicatedFromContentId);
            $copy = $model->content()->withoutGlobalScopes()->first();
            $model->replicatedFromContentId = null;

            if ($source && $copy) {
                app(ContentElementEditorService::class)->copyElements($source, $copy);
            }
        });

        static::saved(function (Model $model): void {
            if ($model->pendingContentAttributes === []) {
                return;
            }

            $content = $model->content()->withoutGlobalScopes()->first();

            if ($content) {
                $content->forceFill($model->pendingContentAttributes)->save();
                $model->setRelation('content', $content);
            }

            $model->pendingContentAttributes = [];
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $content = $model->content()->withoutGlobalScopes()->first();

            if (! $content) {
                return;
            }

            // Through the models, so the element hooks (element collections) run.
            $content->elements()->get()->each->delete();
            $content->delete();
        });
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * The elements of the content, ordered by sort.
     */
    public function elements(): HasMany
    {
        return $this->hasMany(ContentElement::class, 'content_id', 'content_id')->orderBy('sort');
    }

    /**
     * Records whose content name contains the term in any language — the name
     * is not a column of the extension table, so a list searches it through
     * this scope.
     */
    public function scopeWhereContentNameContains(Builder $query, string $search, string $boolean = 'and'): void
    {
        $query->has('content', '>=', 1, $boolean, function (Builder $content) use ($search): void {
            $content->where(function (Builder $names) use ($search): void {
                foreach (CmsLanguageCodes::known() as $index => $code) {
                    ColumnFilterParser::applyLikeContains($names, "name->{$code}", $search, $index === 0 ? 'and' : 'or');
                }
            });
        });
    }

    /**
     * Order by the content name in the given language.
     */
    public function scopeOrderByContentName(Builder $query, string $language, string $direction = 'asc'): void
    {
        $query->orderBy(
            Content::query()->withoutGlobalScopes()
                ->select("name->{$language}")
                ->whereColumn('contents.id', $this->qualifyColumn('content_id')),
            $direction === 'desc' ? 'desc' : 'asc',
        );
    }

    public function getAttribute($key)
    {
        if (is_string($key) && in_array($key, static::contentAttributes(), true)) {
            return $this->contentAttribute($key);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (is_string($key) && in_array($key, static::contentAttributes(), true)) {
            $this->pendingContentAttributes[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * The content attributes are part of the model's array form, so a detail
     * that mounts `$model->toArray()` gets them like its own columns.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach (static::contentAttributes() as $key) {
            $attributes[$key] = $this->contentAttribute($key);
        }

        return $attributes;
    }

    /**
     * The content relation is never serialized: its attributes are already
     * merged in, and a mounted form must not carry a `content` key back into
     * a mass assignment.
     *
     * @return array<string, mixed>
     */
    public function relationsToArray(): array
    {
        $relations = parent::relationsToArray();

        unset($relations['content']);

        return $relations;
    }

    /**
     * Pending value first, then a value a query selected onto the row (a
     * listing joining `contents`), then the content itself.
     */
    protected function contentAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->pendingContentAttributes)) {
            return $this->pendingContentAttributes[$key];
        }

        if (array_key_exists($key, $this->attributes)) {
            $content = new Content();
            $content->setRawAttributes([$key => $this->attributes[$key]]);

            return $content->getAttribute($key);
        }

        if (! $this->getAttributeFromArray('content_id')) {
            return null;
        }

        if (! $this->relationLoaded('content')) {
            $this->setRelation('content', $this->content()->withoutGlobalScopes()->first());
        }

        return $this->getRelation('content')?->getAttribute($key);
    }
}
