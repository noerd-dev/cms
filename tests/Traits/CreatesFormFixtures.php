<?php

declare(strict_types=1);

namespace Noerd\Cms\Tests\Traits;

use Illuminate\Support\Facades\File;
use Noerd\Cms\Models\FormType;

/**
 * Form types bound to a throwaway form YAML in the testing storage — the
 * submission is fail-closed, a form type without a readable definition takes
 * nothing.
 */
trait CreatesFormFixtures
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function formTypeWithYaml(int $tenantId, string $key = 'contact', array $attributes = [], ?string $yaml = null): FormType
    {
        $directory = storage_path('framework/testing/zz-cms-forms');
        File::ensureDirectoryExists($directory);
        $path = $directory . '/' . $tenantId . '-' . $key . '.yml';

        File::put($path, $yaml ?? implode("\n", [
            "key: {$key}",
            'title: Contact',
            'fields:',
            '  - name: name',
            '    validation:',
            '      - string',
            "      - 'max:255'",
            '  - name: email',
            '    validation:',
            '      - email',
            '  - name: message',
            '    validation:',
            '      - string',
        ]));

        return FormType::factory()->create([
            'tenant_id' => $tenantId,
            'key' => $key,
            'yml_path' => $path,
            ...$attributes,
        ]);
    }
}
