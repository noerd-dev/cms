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

        // Only tenants that actually run the CMS app get form types — syncing
        // for every tenant would create orphan rows.
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::whereHas('tenantApps', fn($query) => $query->where('name', 'CMS'))->get();

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

            // Sync the form type. Store the path relative to the project root so
            // release-directory deploys keep resolving it, and only overwrite
            // columns the YAML actually declares — a re-sync must not wipe
            // values an admin edited in the UI.
            $formTypeData = [
                'title' => $config['title'] ?? $key,
                'yml_path' => str_replace(base_path() . '/', '', $ymlFile),
                'yml_synced_at' => now(),
            ];

            foreach (['description', 'send_email', 'email_subject', 'email_body', 'notification_email'] as $column) {
                if (array_key_exists($column, $config)) {
                    $formTypeData[$column] = $config[$column];
                }
            }

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
