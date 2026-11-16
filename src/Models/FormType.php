<?php

declare(strict_types=1);

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Cms\Database\Factories\FormTypeFactory;
use Noerd\Cms\Forms\FormDefinition;
use Noerd\Helpers\FormatHelper;
use Noerd\Models\Tenant;
use Noerd\Traits\BelongsToTenant;

class FormType extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'cms_form_types';

    protected $guarded = [];

    /**
     * Get available email placeholders with descriptions (translation keys).
     *
     * @return array<string, string>
     */
    public static function getEmailPlaceholders(): array
    {
        return [
            '{{form_title}}' => 'Form title',
            '{{submission_date}}' => 'Submission date',
            '{{field:*}}' => 'Form fields (e.g. {{field:name}}, {{field:email}})',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function formRequests(): HasMany
    {
        return $this->hasMany(FormRequest::class, 'form_type_id', 'id');
    }

    /**
     * The form YAML this type was synced from, read in the current vocabulary
     * (FormDefinition) — null when the file is missing or broken. The path is
     * stored relative to the project root (written by FormTypeSyncService,
     * never by a screen).
     */
    public function definition(): ?FormDefinition
    {
        if (! is_string($this->yml_path) || $this->yml_path === '') {
            return null;
        }

        return FormDefinition::fromFile(str_starts_with($this->yml_path, '/') ? $this->yml_path : base_path($this->yml_path));
    }

    /**
     * The field of a submission holding the visitor's address for the
     * confirmation.
     */
    public function confirmationField(): string
    {
        $field = $this->confirmation_field;

        return is_string($field) && $field !== '' ? $field : FormDefinition::DEFAULT_CONFIRMATION_FIELD;
    }

    /**
     * @param  Builder<FormType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Replace placeholders in email content with actual form request values.
     *
     * With $escapeHtml every substituted value is HTML-escaped — mandatory when
     * the result is rendered unescaped (the HTML email body): submitted form
     * values are attacker-controlled and must never reach an HTML sink raw.
     */
    public function replacePlaceholders(FormRequest $formRequest, string $content, bool $escapeHtml = false): string
    {
        $escape = fn(string $value): string => $escapeHtml ? e($value) : $value;

        // The confirmation mail leaves the system: dates follow the tenant locale.
        $tenantId = $formRequest->tenant_id ?? $this->tenant_id;

        $replacements = [
            '{{form_title}}' => $escape((string) $this->title),
            '{{submission_date}}' => $escape(FormatHelper::documentDateTime(
                $formRequest->created_at,
                $tenantId === null ? null : (int) $tenantId,
            )),
        ];

        // Replace static placeholders
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        // Replace dynamic field placeholders ({{field:name}})
        if (is_array($formRequest->data)) {
            foreach ($formRequest->data as $fieldName => $fieldValue) {
                if (is_array($fieldValue)) {
                    $fieldValue = implode(', ', array_filter($fieldValue, 'is_scalar'));
                }

                if (! is_scalar($fieldValue)) {
                    continue;
                }

                $placeholder = '{{field:' . $fieldName . '}}';
                $content = str_replace($placeholder, $escape((string) $fieldValue), $content);
            }
        }

        return $content;
    }

    /**
     * Get dynamic field placeholders from YML config.
     *
     * @return array<string, string>
     */
    public function getFieldPlaceholders(): array
    {
        $placeholders = [];

        foreach ($this->definition()?->fields() ?? [] as $field) {
            $placeholders['{{field:' . $field['name'] . '}}'] = (string) ($field['label'] ?? $field['name']);
        }

        return $placeholders;
    }

    protected static function newFactory(): FormTypeFactory
    {
        return FormTypeFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'send_email' => 'boolean',
            'send_confirmation' => 'boolean',
            'yml_synced_at' => 'datetime',
        ];
    }
}
