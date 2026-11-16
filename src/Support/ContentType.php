<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * One registered kind of content: the extension model that carries its own
 * columns, the record route/component that opens it and the languages its
 * document is written in.
 */
final readonly class ContentType
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $modelClass,
        public ?string $detailRoute = null,
        public ?string $detailComponent = null,
        public ContentLanguageSource $languageSource = ContentLanguageSource::Cms,
    ) {}
}
