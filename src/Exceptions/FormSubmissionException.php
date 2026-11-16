<?php

declare(strict_types=1);

namespace Noerd\Cms\Exceptions;

use RuntimeException;

/**
 * A form submission the CMS refuses as a whole (unknown or inactive form,
 * missing form definition, payload too large) — nothing is stored, no mail
 * goes out. The message is an English translation key.
 */
final class FormSubmissionException extends RuntimeException
{
    public static function unknownForm(): self
    {
        return new self('Unknown form type.');
    }

    public static function missingDefinition(): self
    {
        return new self('The form definition is missing.');
    }

    public static function payloadTooLarge(): self
    {
        return new self('Payload too large.');
    }
}
