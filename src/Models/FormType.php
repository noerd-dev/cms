<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Noerd\Noerd\Models\Tenant;
use Noerd\Website\Models\FormRequest;
use Symfony\Component\Yaml\Yaml;

class FormType extends Model
{
    protected $table = 'form_types';

    protected $fillable = [
        'tenant_id',
        'key',
        'title',
        'description',
        'is_active',
        'sort_order',
        'send_email',
        'email_subject',
        'email_body',
        'notification_email',
        'yml_path',
        'yml_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'send_email' => 'boolean',
            'sort_order' => 'integer',
            'yml_synced_at' => 'datetime',
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
        if (! $this->yml_path || ! File::exists($this->yml_path)) {
            return null;
        }

        try {
            return Yaml::parseFile($this->yml_path);
        } catch (\Exception $e) {
            logger()->error('Failed to load YML config for FormType', [
                'form_type_id' => $this->id,
                'yml_path' => $this->yml_path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get available email placeholders with descriptions
     */
    public static function getEmailPlaceholders(): array
    {
        return [
            '{{form_title}}' => 'Formular-Titel',
            '{{submission_date}}' => 'Datum der Einreichung',
            '{{field:*}}' => 'Formularfelder (z.B. {{field:name}}, {{field:email}})',
        ];
    }

    /**
     * Replace placeholders in email content with actual form request values
     */
    public function replacePlaceholders(FormRequest $formRequest, string $content): string
    {
        $replacements = [
            '{{form_title}}' => $this->title,
            '{{submission_date}}' => $formRequest->created_at->format('d.m.Y H:i'),
        ];

        // Replace static placeholders
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        // Replace dynamic field placeholders ({{field:name}})
        if (is_array($formRequest->data)) {
            foreach ($formRequest->data as $fieldName => $fieldValue) {
                $placeholder = '{{field:' . $fieldName . '}}';
                $content = str_replace($placeholder, (string) $fieldValue, $content);
            }
        }

        return $content;
    }

    /**
     * Get dynamic field placeholders from YML config
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
}
