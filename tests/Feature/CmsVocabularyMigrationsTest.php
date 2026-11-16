<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\FormType;
use Noerd\Cms\Services\Maintenance\FieldTypeRenameRepair;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

/*
 | The data migrations of 0.6 with representative rows: the camelCase field
 | types in stored schemas, and the separated form confirmation.
 */
it('renames the kebab-case field types in stored collection schemas, nested ones included', function (): void {
    $tenant = Tenant::factory()->create();
    $definition = CollectionDefinition::query()->ofTenant($tenant->id)->create([
        'tenant_id' => $tenant->id,
        'filename' => 'zz-services',
        'key' => 'ZZ_SERVICES',
        'title' => 'Service',
        'title_list' => 'Services',
        'fields' => [
            ['name' => 'title', 'type' => 'translatableText'],
            ['name' => 'items', 'type' => 'element-collection', 'fields' => [
                ['name' => 'inner', 'type' => 'element-collection', 'fields' => [['name' => 'x', 'type' => 'text']]],
            ]],
            ['name' => 'home', 'type' => 'homepage-select'],
        ],
    ]);

    $repair = app(FieldTypeRenameRepair::class);

    expect($repair->run())->toBe(1);

    $fields = json_decode((string) DB::table('cms_collection_definitions')->where('id', $definition->id)->value('fields'), true);

    expect($fields[0]['type'])->toBe('translatableText')
        ->and($fields[1]['type'])->toBe('elementCollection')
        ->and($fields[1]['fields'][0]['type'])->toBe('elementCollection')
        ->and($fields[1]['fields'][0]['fields'][0]['type'])->toBe('text')
        ->and($fields[2]['type'])->toBe('homepageSelect')
        // Idempotent: nothing left to rewrite.
        ->and($repair->run())->toBe(0);
});

it('keeps the visitor confirmation of form types that mailed before 0.6', function (): void {
    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_11_16_000002_separate_form_confirmation_from_notification.php';
    $tenant = Tenant::factory()->create();

    // The table as 0.5 left it.
    $migration->down();
    DB::table('cms_form_types')->insert([
        ['tenant_id' => $tenant->id, 'key' => 'zz-on', 'title' => 'On', 'yml_path' => 'x.yml', 'send_email' => true, 'email_subject' => 'S', 'email_body' => '<p>B</p>'],
        ['tenant_id' => $tenant->id, 'key' => 'zz-off', 'title' => 'Off', 'yml_path' => 'y.yml', 'send_email' => false, 'email_subject' => 'S', 'email_body' => '<p>B</p>'],
    ]);

    $migration->up();

    $on = FormType::query()->ofTenant($tenant->id)->where('key', 'zz-on')->first();
    $off = FormType::query()->ofTenant($tenant->id)->where('key', 'zz-off')->first();

    expect($on->send_confirmation)->toBeTrue()
        ->and($on->confirmation_subject)->toBe('S')
        ->and($on->confirmation_body)->toBe('<p>B</p>')
        ->and($on->send_email)->toBeTrue()
        ->and($on->is_active)->toBeTrue()
        ->and($off->send_confirmation)->toBeFalse()
        ->and($off->confirmation_subject)->toBeNull();

    // A second run changes nothing an admin decided meanwhile.
    $on->update(['send_confirmation' => false]);
    $migration->up();

    expect($on->fresh()->send_confirmation)->toBeFalse();
});
