<?php

declare(strict_types=1);

namespace Noerd\Cms\Forms;

/**
 * Rewrites the form YAML of `app-configs/cms/forms/*.yml` from the earlier
 * snake_case vocabulary to the 0.6 one, line by line — comments, order and
 * formatting stay. Idempotent: a file in the current vocabulary is not touched.
 *
 * - the keys of FormDefinition::LEGACY_KEYS (top level) and
 *   FormDefinition::LEGACY_FIELD_KEYS (anywhere) get their camelCase name
 * - up to 0.5 `send_email: true` sent the same mail to the recipients AND to
 *   the visitor's `email`. 0.6 separates the two; a file switching it on and
 *   declaring no confirmation of its own gets `sendConfirmation: true` plus a
 *   copy of the subject and the body as `confirmationSubject` /
 *   `confirmationBody`, so the visitor keeps receiving a mail. Review that
 *   text: it was written for the recipients.
 *
 * @internal
 */
final class FormYamlMigrator
{
    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param  list<string>  $paths  directories (their `*.yml` files) or files
     * @return list<string> the files that were (or, in a dry run, would be) rewritten
     */
    public function migrate(array $paths, bool $dryRun = false): array
    {
        $changed = [];

        foreach ($this->files($paths) as $path) {
            $source = (string) file_get_contents($path);
            $migrated = $this->migrateSource($source);

            if ($migrated === $source) {
                continue;
            }

            if (! $dryRun) {
                file_put_contents($path, $migrated);
            }

            $changed[] = $path;
        }

        return $changed;
    }

    public function migrateSource(string $source): string
    {
        $bom = str_starts_with($source, self::BOM) ? self::BOM : '';
        $lines = preg_split('/(?<=\n)/', $bom === '' ? $source : mb_substr($source, 1)) ?: [];
        $legacyTop = FormDefinition::LEGACY_KEYS;
        $legacyField = FormDefinition::LEGACY_FIELD_KEYS;
        $hadSendEmail = false;
        $sendEmailOn = false;
        $declaresConfirmation = false;
        $blocks = [];
        $current = null;
        $inScalarBlock = null;

        foreach ($lines as $index => $line) {
            // The content of a block scalar (`emailBody: |`) is text, never a key.
            if ($inScalarBlock !== null) {
                if (mb_trim($line) === '' || $this->indentOf($line) > $inScalarBlock) {
                    if ($current !== null) {
                        $blocks[$current][] = $index;
                    }

                    continue;
                }

                $inScalarBlock = null;
            }

            if (! preg_match('/^(\s*)(-\s+)?(["\']?)([A-Za-z_][A-Za-z0-9_]*)\3(\s*):(.*)$/s', $line, $match)) {
                if ($current !== null && mb_trim($line) !== '' && $this->indentOf($line) > 0) {
                    $blocks[$current][] = $index;
                }

                continue;
            }

            [$all, $indent, $dash, $quote, $key, $space, $rest] = $match;
            $topLevel = $indent === '' && $dash === '';
            $newKey = $key;

            if ($topLevel && isset($legacyTop[$key])) {
                $newKey = $legacyTop[$key];
            } elseif (! $topLevel && isset($legacyField[$key])) {
                $newKey = $legacyField[$key];
            }

            if ($topLevel) {
                $current = $newKey;
                $blocks[$current] = [$index];

                if ($newKey === 'sendEmail') {
                    $hadSendEmail = $key === 'send_email';
                    $sendEmailOn = preg_match('/^\s*(true|yes|on)\s*(#.*)?$/i', mb_rtrim($rest)) === 1;
                }

                if (in_array($newKey, ['sendConfirmation', 'confirmationSubject', 'confirmationBody'], true)) {
                    $declaresConfirmation = true;
                }
            } elseif ($current !== null) {
                $blocks[$current][] = $index;
            }

            if (preg_match('/^\s*[|>][+-]?\d*\s*(#.*)?$/', mb_rtrim($rest)) === 1) {
                $inScalarBlock = mb_strlen($indent) + mb_strlen($dash);
            }

            if ($newKey !== $key) {
                $lines[$index] = $indent . $dash . $quote . $newKey . $quote . $space . ':' . $rest;
            }
        }

        if ($hadSendEmail && $sendEmailOn && ! $declaresConfirmation) {
            $lines = $this->addConfirmation($lines, $blocks);
        }

        return $bom . implode('', $lines);
    }

    /**
     * `sendConfirmation: true` after `sendEmail`, and a copy of the subject and
     * the body (with their block scalars) under the confirmation keys at the
     * end of the file.
     *
     * @param  list<string>  $lines
     * @param  array<string, list<int>>  $blocks  top-level key => its line indexes
     * @return list<string>
     */
    private function addConfirmation(array $lines, array $blocks): array
    {
        $eol = str_contains(implode('', $lines), "\r\n") ? "\r\n" : "\n";
        $copies = [];

        foreach (['emailSubject' => 'confirmationSubject', 'emailBody' => 'confirmationBody'] as $from => $to) {
            foreach ($blocks[$from] ?? [] as $position => $index) {
                $line = $lines[$index];

                if ($position === 0) {
                    $line = preg_replace('/^' . preg_quote($from, '/') . '/', $to, $line) ?? $line;
                }

                $copies[] = $line;
            }
        }

        $sendEmailLine = $blocks['sendEmail'][0];
        $lines[$sendEmailLine] = mb_rtrim($lines[$sendEmailLine], "\r\n") . $eol . 'sendConfirmation: true' . $eol;

        if ($copies !== []) {
            $last = count($lines) - 1;
            $lines[$last] = mb_rtrim($lines[$last], "\r\n") . $eol;

            foreach ($copies as $copy) {
                $lines[] = mb_rtrim($copy, "\r\n") . $eol;
            }
        }

        return array_values($lines);
    }

    private function indentOf(string $line): int
    {
        return mb_strlen($line) - mb_strlen(mb_ltrim($line, ' '));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                foreach (glob(mb_rtrim($path, '/') . '/*.yml') ?: [] as $file) {
                    $files[] = $file;
                }
            } elseif (is_file($path)) {
                $files[] = $path;
            }
        }

        return array_values(array_unique($files));
    }
}
