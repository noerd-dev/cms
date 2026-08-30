<?php

namespace Noerd\Cms\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Noerd\Cms\Database\Factories\FormTypeFactory;
use Noerd\Models\Tenant;
use Noerd\Traits\BelongsToTenant;
use Symfony\Component\Yaml\Yaml;

class FormType extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'form_types';

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
     * Load YML configuration from file
     */
    public function loadYmlConfig(): ?array
    {
        if (! $this->yml_path) {
            return null;
        }

        // Support both relative and absolute paths
        $fullPath = str_starts_with($this->yml_path, '/')
            ? $this->yml_path
            : base_path($this->yml_path);

        if (! File::exists($fullPath)) {
            return null;
        }

        try {
            return Yaml::parseFile($fullPath);
        } catch (Exception $e) {
            logger()->error('Failed to load YML config for FormType', [
                'form_type_id' => $this->id,
                'yml_path' => $fullPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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

        $replacements = [
            '{{form_title}}' => $escape((string) $this->title),
            '{{submission_date}}' => $escape($formRequest->created_at->format('d.m.Y H:i')),
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
        $config = $this->loadYmlConfig();
        if (! $config || ! isset($config['fields'])) {
            return [];
        }

        $placeholders = [];
        foreach ($config['fields'] as $field) {
            $fieldName = $field['name'] ?? null;
            $fieldLabel = $field['label'] ?? $fieldName;
            if ($fieldName) {
                $placeholders["{{field:{$fieldName}}}"] = $fieldLabel;
            }
        }

        return $placeholders;
    }

    protected static function newFactory()
    {
        return FormTypeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'send_email' => 'boolean',
            'yml_synced_at' => 'datetime',
        ];
    }
}
