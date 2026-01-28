<?php

namespace Noerd\Cms\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Noerd\Cms\Models\FormType;
use Noerd\Models\Tenant;
use Symfony\Component\Yaml\Yaml;

class FormTypeSyncService
{
    protected int $synced = 0;
    protected int $skipped = 0;
    protected int $errors = 0;
    protected array $messages = [];

    /**
     * Sync form types from YML files to database
     */
    public function sync(?int $tenantId = null, bool $force = false): array
    {
        $this->synced = 0;
        $this->skipped = 0;
        $this->errors = 0;
        $this->messages = [];

        $formFilesPath = base_path('app-configs/cms/forms');

        if (! File::exists($formFilesPath)) {
            $this->messages[] = "Forms directory not found: {$formFilesPath}";

            return $this->getResults();
        }

        $ymlFiles = File::glob($formFilesPath . '/*.yml');

        if (empty($ymlFiles)) {
            $this->messages[] = 'No YML files found in app-configs/cms/forms/';

            return $this->getResults();
        }

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->messages[] = 'No tenants found.';

            return $this->getResults();
        }

        foreach ($ymlFiles as $ymlFile) {
            foreach ($tenants as $tenant) {
                $this->syncFormTypeForTenant($ymlFile, $tenant, $force);
            }
        }

        return $this->getResults();
    }

    /**
     * Sync a single form type for a tenant
     */
    protected function syncFormTypeForTenant(string $ymlFile, Tenant $tenant, bool $force): void
    {
        try {
            $config = Yaml::parseFile($ymlFile);

            if (! isset($config['key'])) {
                $this->errors++;
                $this->messages[] = "YML file missing 'key' field: {$ymlFile}";

                return;
            }

            $key = $config['key'];
            $fileModifiedTime = Carbon::createFromTimestamp(File::lastModified($ymlFile));

            // Check if we need to sync
            $existingFormType = FormType::where('tenant_id', $tenant->id)
                ->where('key', $key)
                ->first();

            if ($existingFormType && ! $force) {
                // Skip if file hasn't been modified since last sync
                if ($existingFormType->yml_synced_at
                    && $existingFormType->yml_synced_at->greaterThanOrEqualTo($fileModifiedTime)) {
                    $this->skipped++;

                    return;
                }
            }

            // Sync the form type
            $formTypeData = [
                'tenant_id' => $tenant->id,
                'key' => $key,
                'title' => $config['title'] ?? $key,
                'description' => $config['description'] ?? null,
                'send_email' => $config['send_email'] ?? false,
                'email_subject' => $config['email_subject'] ?? null,
                'email_body' => $config['email_body'] ?? null,
                'notification_email' => $config['notification_email'] ?? null,
                'yml_path' => $ymlFile,
                'yml_synced_at' => now(),
            ];

            FormType::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'key' => $key,
                ],
                $formTypeData,
            );

            $this->synced++;
            $this->messages[] = "Synced form type '{$key}' for tenant {$tenant->id}";
        } catch (Exception $e) {
            $this->errors++;
            $this->messages[] = "Error syncing {$ymlFile} for tenant {$tenant->id}: {$e->getMessage()}";

            logger()->error('FormTypeSyncService error', [
                'file' => $ymlFile,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get sync results
     */
    protected function getResults(): array
    {
        return [
            'synced' => $this->synced,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'messages' => $this->messages,
        ];
    }
}
