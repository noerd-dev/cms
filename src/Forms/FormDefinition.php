<?php

declare(strict_types=1);

namespace Noerd\Cms\Forms;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * One form of `app-configs/cms/forms/*.yml`, read in the current (0.6) YAML
 * vocabulary — camelCase keys only. The earlier snake_case keys are not read
 * (`noerd:update-cms` / `cms:migrate-form-yaml` rewrite them); a file still
 * carrying one reports it through outdatedKeys().
 *
 *     key: contact
 *     title: Contact Form
 *     sendEmail: true              # the notification to the form recipients
 *     emailSubject: 'New request: {{field:name}}'
 *     emailBody: '<p>…</p>'
 *     notificationEmail: office@example.com
 *     sendConfirmation: true       # the confirmation to the visitor
 *     confirmationField: email     # the field holding the visitor's address
 *     confirmationSubject: 'Thank you for your message'
 *     confirmationBody: '<p>…</p>'
 *     successMessage: 'Thank you!'
 *     submitButtonText: 'Send'
 *     fields:
 *       - name: email
 *         type: email
 *         required: true           # or `required` in `validation` — one implies the other
 *         validation: [email, 'max:255']
 *         errorMessages:
 *           required: 'Please enter your email address.'
 */
final readonly class FormDefinition
{
    /**
     * Top-level keys that map onto a column of `cms_form_types`: YAML key =>
     * column. A key the file does not declare leaves the column alone, so a
     * re-sync keeps what an admin edited in the UI.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'title' => 'title',
        'description' => 'description',
        'sendEmail' => 'send_email',
        'emailSubject' => 'email_subject',
        'emailBody' => 'email_body',
        'notificationEmail' => 'notification_email',
        'sendConfirmation' => 'send_confirmation',
        'confirmationSubject' => 'confirmation_subject',
        'confirmationBody' => 'confirmation_body',
        'confirmationField' => 'confirmation_field',
    ];

    /**
     * The earlier (snake_case) top-level keys and their current names.
     *
     * @var array<string, string>
     */
    public const LEGACY_KEYS = [
        'send_email' => 'sendEmail',
        'email_subject' => 'emailSubject',
        'email_body' => 'emailBody',
        'notification_email' => 'notificationEmail',
        'success_message' => 'successMessage',
        'submit_button_text' => 'submitButtonText',
    ];

    /**
     * The earlier key of a field and its current name.
     *
     * @var array<string, string>
     */
    public const LEGACY_FIELD_KEYS = [
        'error_messages' => 'errorMessages',
    ];

    /** The field holding the visitor's address when the file names none. */
    public const DEFAULT_CONFIRMATION_FIELD = 'email';

    /**
     * @param  array<string, mixed>  $config  the parsed YAML
     */
    public function __construct(public array $config) {}

    /**
     * The definition of a YAML file, or null when the file is missing or no
     * valid YAML mapping.
     */
    public static function fromFile(string $path): ?self
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $config = Yaml::parseFile($path);
        } catch (ParseException $e) {
            logger()->error('Failed to parse a CMS form YAML', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }

        return is_array($config) ? new self($config) : null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public static function isRequired(array $field): bool
    {
        return ($field['required'] ?? false) === true || in_array('required', self::ruleList($field), true);
    }

    public function key(): ?string
    {
        $key = $this->config['key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function title(): string
    {
        return (string) ($this->config['title'] ?? $this->key() ?? '');
    }

    public function successMessage(): ?string
    {
        return is_string($this->config['successMessage'] ?? null) ? $this->config['successMessage'] : null;
    }

    public function submitButtonText(): ?string
    {
        return is_string($this->config['submitButtonText'] ?? null) ? $this->config['submitButtonText'] : null;
    }

    /**
     * The fields with a name, each with `required` resolved (see rules()).
     *
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ((array) ($this->config['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_string($field['name'] ?? null) || $field['name'] === '') {
                continue;
            }

            $field['required'] = self::isRequired($field);
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    public function fieldNames(): array
    {
        return array_column($this->fields(), 'name');
    }

    /**
     * The Laravel rules and custom messages of the fields. `required: true`
     * and a `required` rule mean the same: either adds the other. A field that
     * is not required is `nullable` unless its rules decide otherwise.
     *
     * @return array{0: array<string, list<mixed>>, 1: array<string, string>}
     */
    public function rules(): array
    {
        $rules = [];
        $messages = [];

        foreach ($this->fields() as $field) {
            $name = $field['name'];
            $fieldRules = self::ruleList($field);

            if ($field['required'] && ! in_array('required', $fieldRules, true)) {
                array_unshift($fieldRules, 'required');
            } elseif (! $field['required'] && ! self::decidesPresence($fieldRules)) {
                array_unshift($fieldRules, 'nullable');
            }

            $rules[$name] = $fieldRules;

            foreach ((array) ($field['errorMessages'] ?? []) as $rule => $message) {
                if (is_string($rule) && is_string($message)) {
                    $messages["{$name}.{$rule}"] = __($message);
                }
            }
        }

        return [$rules, $messages];
    }

    /**
     * The column values this file declares (COLUMNS), for the form type row.
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        $columns = [];

        foreach (self::COLUMNS as $key => $column) {
            if (array_key_exists($key, $this->config)) {
                $columns[$column] = $this->config[$key];
            }
        }

        return $columns;
    }

    /**
     * The earlier keys this file still carries (top level and in its fields),
     * e.g. `send_email` — 0.6 reads none of them.
     *
     * @return list<string>
     */
    public function outdatedKeys(): array
    {
        $outdated = array_values(array_intersect(array_keys(self::LEGACY_KEYS), array_keys($this->config)));

        foreach ((array) ($this->config['fields'] ?? []) as $field) {
            if (is_array($field)) {
                foreach (array_keys(self::LEGACY_FIELD_KEYS) as $legacy) {
                    if (array_key_exists($legacy, $field) && ! in_array($legacy, $outdated, true)) {
                        $outdated[] = $legacy;
                    }
                }
            }
        }

        return $outdated;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    private static function ruleList(array $field): array
    {
        $validation = $field['validation'] ?? [];

        if (is_string($validation)) {
            $validation = $validation === '' ? [] : explode('|', $validation);
        }

        return array_values(array_filter((array) $validation, fn(mixed $rule): bool => is_string($rule) && $rule !== ''));
    }

    /**
     * @param  list<mixed>  $rules
     */
    private static function decidesPresence(array $rules): bool
    {
        foreach ($rules as $rule) {
            $name = is_string($rule) ? explode(':', $rule, 2)[0] : '';

            if (in_array($name, ['nullable', 'sometimes', 'present', 'accepted'], true) || str_starts_with($name, 'required')) {
                return true;
            }
        }

        return false;
    }
}
