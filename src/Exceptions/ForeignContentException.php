<?php

declare(strict_types=1);

namespace Noerd\Cms\Exceptions;

use RuntimeException;

/**
 * A record that extends a content (ExtendsContent) was pointed at a content
 * that is not its own: one of another tenant, of another content type, one
 * another record already extends — or a saved record's content was swapped.
 * A content belongs to exactly one record for its whole life.
 */
final class ForeignContentException extends RuntimeException
{
    public static function forAdoption(string $modelClass, mixed $contentId): self
    {
        return new self(sprintf(
            '%s may not extend content %s: it belongs to another tenant, another content type or another record.',
            $modelClass,
            var_export($contentId, true),
        ));
    }

    public static function forSwap(string $modelClass, mixed $from, mixed $to): self
    {
        return new self(sprintf(
            '%s may not change its content from %s to %s — a record keeps the content it was created with.',
            $modelClass,
            var_export($from, true),
            var_export($to, true),
        ));
    }

    public static function forDocument(mixed $contentId): self
    {
        return new self(sprintf(
            'The document of content %s may not be written here: it is not the content of the edited record.',
            var_export($contentId, true),
        ));
    }
}
