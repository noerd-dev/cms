<?php

declare(strict_types=1);

namespace Noerd\Cms\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Exceptions\ForeignContentException;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Services\ContentElementEditorService;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\ColumnFilterParser;

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
            $contentId = $model->getAttributeFromArray('content_id');

            // A record may be created onto an existing content (a data import)
            // only when that content is free: of the record's tenant and type,
            // and extended by no other record. Never another tenant's content.
            if ($contentId) {
                if (! static::contentIsAdoptable($model, $contentId)) {
                    throw ForeignContentException::forAdoption($model::class, $contentId);
                }

                return;
            }

            $content = Content::query()->create([
                'tenant_id' => static::contentTenantId($model),
                'type' => static::contentType(),
                ...$model->pendingContentAttributes,
            ]);

            $model->setAttribute('content_id', $content->getKey());
            $model->setRelation('content', $content);
            $model->pendingContentAttributes = [];
        });

        // A saved record keeps the content it was created with: a changed
        // content_id would hand the record (and every write through it — name,
        // document) someone else's content. A legacy row without a content may
        // adopt a free one of its own tenant and type.
        static::updating(function (Model $model): void {
            if (! $model->isDirty('content_id')) {
                return;
            }

            $original = $model->getOriginal('content_id');
            $contentId = $model->getAttributeFromArray('content_id');

            if ($original === null && $contentId && static::contentIsAdoptable($model, $contentId)) {
                return;
            }

            throw ForeignContentException::forSwap($model::class, $original, $contentId);
        });

        // A replica never shares the original's content: it gets its own, with
        // the same name (unless set) and copies of the elements.
        static::replicating(function (Model $replica): void {
            $sourceId = $replica->getAttributeFromArray('content_id');

            if (! $sourceId) {
                return;
            }

            $source = static::ownContentQuery($replica)->find($sourceId);

            foreach (static::contentAttributes() as $key) {
                if (! array_key_exists($key, $replica->pendingContentAttributes)) {
                    $replica->pendingContentAttributes[$key] = $source?->getAttribute($key);
                }
            }

            $replica->offsetUnset('content_id');
            $replica->unsetRelation('content');
            $replica->replicatedFromContentId = $source ? (int) $source->getKey() : null;
        });

        static::created(function (Model $model): void {
            if ($model->replicatedFromContentId === null) {
                return;
            }

            $source = static::ownContentQuery($model)->find($model->replicatedFromContentId);
            $copy = static::ownContentQuery($model)->find($model->getAttributeFromArray('content_id'));
            $model->replicatedFromContentId = null;

            if ($source && $copy) {
                app(ContentElementEditorService::class)->copyElements($source, $copy);
            }
        });

        static::saved(function (Model $model): void {
            if ($model->pendingContentAttributes === []) {
                return;
            }

            $content = $model->ownContent();

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

            $content = $model->ownContent();

            if (! $content) {
                return;
            }

            // Through the models, so the element hooks (element collections) run.
            $content->elements()->get()->each->delete();
            $content->delete();
        });
    }

    /**
     * The record's own content: the one its content_id names, IN THE RECORD'S
     * TENANT and of its content type — never the row of another tenant, whatever
     * content_id says. Resolved without the current tenant context, so headless
     * callers (jobs, imports) find it too.
     */
    public function ownContent(): ?Content
    {
        $contentId = $this->getAttributeFromArray('content_id');

        if (! $contentId) {
            return null;
        }

        return static::ownContentQuery($this)->find($contentId);
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
            Content::query()->acrossTenants()
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
     * Whether $contentId may become the content of $model: a content of the
     * record's tenant and content type that no other record extends.
     */
    protected static function contentIsAdoptable(Model $model, mixed $contentId): bool
    {
        if (! is_numeric($contentId)) {
            return false;
        }

        if (! static::ownContentQuery($model)->whereKey((int) $contentId)->exists()) {
            return false;
        }

        $takenBy = $model->newQueryWithoutScopes()->where($model->qualifyColumn('content_id'), (int) $contentId);

        if ($model->exists) {
            $takenBy->whereKeyNot($model->getKey());
        }

        return ! $takenBy->exists();
    }

    /**
     * The contents a record of this type may own: those of its tenant and type.
     */
    protected static function ownContentQuery(Model $model): Builder
    {
        $tenantId = static::contentTenantId($model);

        if ($tenantId === null) {
            return Content::query()->acrossTenants()->whereRaw('1 = 0');
        }

        return Content::query()
            ->ofTenant($tenantId)
            ->where('type', static::contentType());
    }

    /**
     * The tenant of the record (the current one while it is being created
     * without a tenant of its own).
     */
    protected static function contentTenantId(Model $model): ?int
    {
        $tenantId = $model->getAttributeFromArray('tenant_id') ?? TenantHelper::currentTenantId();

        return $tenantId === null ? null : (int) $tenantId;
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
            $this->setRelation('content', $this->ownContent());
        }

        return $this->getRelation('content')?->getAttribute($key);
    }
}
