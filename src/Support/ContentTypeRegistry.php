<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Noerd\Cms\Models\Content;

/**
 * The kinds of content the installed modules contribute. A module registers
 * its type in its provider's boot():
 *
 *     app(ContentTypeRegistry::class)->register(
 *         key: 'blog.post',
 *         label: 'Blog Post',
 *         modelClass: Post::class,
 *         detailRoute: 'blog.post.detail',
 *         detailComponent: 'blog::post-detail',
 *         languageSource: ContentLanguageSource::Cms,
 *     );
 *
 * `languageSource` names the languages the type's document is written in:
 * the tenant's website languages (`Cms`, the default — a type the website
 * renders) or the backend's setup languages (`Setup` — content of a module
 * that is no website). The editor follows it (ContentLanguageSource).
 *
 * It is the single place a content's `type` maps to its extension model and
 * to the screen that opens it.
 */
final class ContentTypeRegistry
{
    /** @var array<string, ContentType> */
    private array $types = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function register(
        string $key,
        string $label,
        string $modelClass,
        ?string $detailRoute = null,
        ?string $detailComponent = null,
        ContentLanguageSource $languageSource = ContentLanguageSource::Cms,
    ): void {
        $this->types[$key] = new ContentType($key, $label, $modelClass, $detailRoute, $detailComponent, $languageSource);
    }

    /**
     * The language source of a type — `Cms` for a type nobody registered.
     */
    public function languageSource(string $key): ContentLanguageSource
    {
        return $this->forType($key)?->languageSource ?? ContentLanguageSource::Cms;
    }

    public function forType(string $key): ?ContentType
    {
        return $this->types[$key] ?? null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function forModel(string $modelClass): ?ContentType
    {
        foreach ($this->types as $type) {
            if ($type->modelClass === $modelClass) {
                return $type;
            }
        }

        return null;
    }

    /**
     * The URL of the screen that opens the content's record (its extension),
     * or null when the type has no registered route or the record is gone.
     */
    public function recordUrl(Content $content): ?string
    {
        $type = $this->forType((string) $content->type);
        $record = $content->extension();

        if (! $type?->detailRoute || ! $record || ! Route::has($type->detailRoute)) {
            return null;
        }

        return route($type->detailRoute, ['modelId' => $record->getKey()]);
    }

    /**
     * @return array<string, ContentType>
     */
    public function types(): array
    {
        return $this->types;
    }
}
