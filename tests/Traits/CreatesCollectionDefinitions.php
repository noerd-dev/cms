<?php

declare(strict_types=1);

namespace Noerd\Cms\Tests\Traits;

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;

/**
 * Seeds collection definitions (the schema rows) together with their parent
 * collection row — the two records every collection screen needs.
 */
trait CreatesCollectionDefinitions
{
    /**
     * A definition plus its parent collection row for the given tenant.
     *
     * @param  array<int, array<string, mixed>>  $fields
     */
    protected function zzCollectionDefinition(int $tenantId, string $filename, array $fields, bool $hasPage = true): Collection
    {
        $key = mb_strtoupper(str_replace('-', '_', $filename));

        CollectionDefinition::create([
            'tenant_id' => $tenantId,
            'filename' => $filename,
            'key' => $key,
            'title' => ucfirst($filename),
            'title_list' => ucfirst($filename),
            'has_page' => $hasPage,
            'fields' => $fields,
        ]);

        return Collection::create([
            'tenant_id' => $tenantId,
            'collection_key' => $key,
            'name' => ucfirst($filename),
        ]);
    }

    /**
     * The "contacts" definition (a page collection with a single name field).
     */
    protected function zzContactsDefinition(int $tenantId): CollectionDefinition
    {
        return CollectionDefinition::create([
            'tenant_id' => $tenantId,
            'filename' => 'contacts',
            'key' => 'CONTACTS',
            'title' => 'Contact',
            'title_list' => 'People',
            'description' => '',
            'has_page' => true,
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
            ],
        ]);
    }
}
