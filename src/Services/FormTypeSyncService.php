<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Noerd\Cms\Forms\FormDefinition;
use Noerd\Cms\Models\FormType;
use Noerd\Models\Tenant;
use Noerd\Support\TenantContext;

class FormTypeSyncService
{
    /** @var \Illuminate\Support\Collection<string, FormType> keyed by \"tenant:key\" */
    protected \Illuminate\Support\Collection $existingFormTypes;

    protected int $synced = 0;
    protected int $skipped = 0;
    protected int $errors = 0;
    protected int $deactivated = 0;
    protected array $messages = [];

    /** @var array<int, list<string>> tenant id => keys of the form YAML found */
    protected array $presentKeys = [];

    /**
     * Sync form types from YML files to database
     */
    public function sync(?int $tenantId = null, bool $force = false): array
    {
        $this->synced = 0;
        $this->skipped = 0;
        $this->errors = 0;
        $this->deactivated = 0;
        $this->messages = [];
        $this->presentKeys = [];

        $formFilesPath = base_path('app-configs/cms/forms');

        $ymlFiles = File::exists($formFilesPath) ? File::glob($formFilesPath . '/*.yml') : [];

        if ($ymlFiles === []) {
            $this->messages[] = 'No YML files found in app-configs/cms/forms/';
        }

        // Only tenants that actually run the CMS app get form types — syncing
        // for every tenant would create orphan rows.
        $tenants = Tenant::query()
            ->whereHas('tenantApps', fn($query) => $query->where('name', 'CMS'))
            ->when($tenantId, fn($query) => $query->where('id', $tenantId))
            ->get();

        if ($tenants->isEmpty()) {
            $this->messages[] = 'No tenants found.';

            return $this->getResults();
        }

        // One query for every existing form type of the tenants instead of one
        // per (file × tenant) pair.
        $this->existingFormTypes = FormType::query()
            ->acrossTenants()
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->get()
            ->keyBy(fn(FormType $formType): string => $formType->tenant_id . ':' . $formType->key);

        foreach ($ymlFiles as $ymlFile) {
            foreach ($tenants as $tenant) {
                TenantContext::run((int) $tenant->id, fn() => $this->syncFormTypeForTenant($ymlFile, $tenant, $force));
            }
        }

        foreach ($tenants as $tenant) {
            TenantContext::run((int) $tenant->id, fn() => $this->deactivateMissingFormTypes($tenant));
        }

        return $this->getResults();
    }

    /**
     * Sync a single form type for a tenant
     */
    protected function syncFormTypeForTenant(string $ymlFile, Tenant $tenant, bool $force): void
    {
        try {
            $definition = FormDefinition::fromFile($ymlFile);
            $key = $definition?->key();

            if ($definition === null || $key === null) {
                $this->errors++;
                $this->messages[] = "YML file unreadable or missing 'key' field: {$ymlFile}";

                return;
            }

            // The file still exists: its form type stays active even when the
            // file has to wait for a fix below.
            $this->presentKeys[(int) $tenant->id][] = $key;

            // 0.6 reads only the camelCase vocabulary — a file still in the
            // earlier one would silently lose its mail settings.
            if (($outdated = $definition->outdatedKeys()) !== []) {
                $this->errors++;
                $this->messages[] = "{$ymlFile} uses outdated keys (" . implode(', ', $outdated) . '): run php artisan cms:migrate-form-yaml';

                return;
            }

            $fileModifiedTime = Carbon::createFromTimestamp(File::lastModified($ymlFile));

            // Check if we need to sync
            $existingFormType = $this->existingFormTypes->get($tenant->id . ':' . $key);

            if ($existingFormType && ! $force && $existingFormType->is_active) {
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
                'title' => $definition->title(),
                ...$definition->columns(),
                'yml_path' => str_replace(base_path() . '/', '', $ymlFile),
                'yml_synced_at' => now(),
                'is_active' => true,
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
     * A form type whose YAML disappeared is switched inactive: the API refuses
     * it and the backend marks it — the row stays, its requests keep their
     * form type.
     */
    protected function deactivateMissingFormTypes(Tenant $tenant): void
    {
        $this->deactivated += FormType::query()
            ->ofTenant((int) $tenant->id)
            ->where('is_active', true)
            ->whereNotIn('key', $this->presentKeys[(int) $tenant->id] ?? [])
            ->update(['is_active' => false]);
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
            'deactivated' => $this->deactivated,
            'messages' => $this->messages,
        ];
    }
}
