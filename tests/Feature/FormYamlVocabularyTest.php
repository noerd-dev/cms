<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Noerd\Cms\Forms\FormDefinition;
use Noerd\Cms\Forms\FormYamlMigrator;
use Symfony\Component\Yaml\Yaml;

uses(Noerd\Cms\Tests\TestCase::class);

/*
 | The form YAML vocabulary of 0.6 (camelCase keys) and the codemod that
 | rewrites a file of the earlier one.
 */
const ZZ_LEGACY_FORM = <<<'YAML'
# A project's own form
key: zz-callback
title: Callback
send_email: true
email_subject: 'Callback: {{field:name}}'
email_body: |
  <p>Please call {{field:name}}.</p>
  <p>send_email: is text here, never a key</p>
notification_email: office@example.com
fields:
  - name: name
    required: true
    validation:
      - required
      - string
    error_messages:
      required: 'Name, please.'
  - name: email
    validation: [email]
success_message: 'Thanks!'
submit_button_text: 'Call me'
YAML;

it('rewrites every earlier key and keeps comments, order and block scalars', function (): void {
    $migrated = app(FormYamlMigrator::class)->migrateSource(ZZ_LEGACY_FORM);
    $config = Yaml::parse($migrated);

    expect($migrated)->toStartWith("# A project's own form\nkey: zz-callback")
        ->and($migrated)->toContain('<p>send_email: is text here, never a key</p>')
        ->and($config)->toHaveKeys(['sendEmail', 'emailSubject', 'emailBody', 'notificationEmail', 'successMessage', 'submitButtonText'])
        ->and($config)->not->toHaveKeys(['send_email', 'email_subject', 'email_body', 'notification_email', 'success_message', 'submit_button_text'])
        ->and($config['fields'][0])->toHaveKey('errorMessages')
        ->and($config['fields'][0])->not->toHaveKey('error_messages')
        ->and((new FormDefinition($config))->outdatedKeys())->toBe([]);
});

it('keeps the visitor mail of a form that sent one before 0.6', function (): void {
    // Up to 0.5 `send_email` also mailed the visitor, with the same texts.
    $config = Yaml::parse(app(FormYamlMigrator::class)->migrateSource(ZZ_LEGACY_FORM));

    expect($config['sendConfirmation'])->toBeTrue()
        ->and($config['confirmationSubject'])->toBe('Callback: {{field:name}}')
        ->and($config['confirmationBody'])->toBe($config['emailBody'])
        ->and($config['emailBody'])->toContain('Please call');
});

it('adds no confirmation to a form that sent no mail', function (): void {
    $config = Yaml::parse(app(FormYamlMigrator::class)->migrateSource("key: zz-off\nsend_email: false\nfields: []\n"));

    expect($config['sendEmail'])->toBeFalse()
        ->and($config)->not->toHaveKey('sendConfirmation');
});

it('is idempotent', function (): void {
    $migrator = app(FormYamlMigrator::class);
    $once = $migrator->migrateSource(ZZ_LEGACY_FORM);

    expect($migrator->migrateSource($once))->toBe($once);
});

it('lists the files it would change in a dry run and writes nothing', function (): void {
    $directory = storage_path('framework/testing/zz-cms-form-vocabulary');
    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);
    File::put($directory . '/legacy.yml', ZZ_LEGACY_FORM);
    File::put($directory . '/current.yml', "key: zz-current\nsendEmail: true\nfields: []\n");

    $migrator = app(FormYamlMigrator::class);

    expect($migrator->migrate([$directory], dryRun: true))->toBe([$directory . '/legacy.yml'])
        ->and(File::get($directory . '/legacy.yml'))->toBe(ZZ_LEGACY_FORM)
        ->and($migrator->migrate([$directory]))->toBe([$directory . '/legacy.yml'])
        ->and($migrator->migrate([$directory]))->toBe([]);

    File::deleteDirectory($directory);
});

it('offers the rewrite as a command with a dry run', function (): void {
    expect(Artisan::call('cms:migrate-form-yaml', ['--dry-run' => true]))->toBe(0);
});

describe('FormDefinition', function (): void {
    it('derives the presence rule from either `required: true` or a `required` rule', function (): void {
        $definition = new FormDefinition(['key' => 'zz', 'fields' => [
            ['name' => 'a', 'required' => true, 'validation' => ['string']],
            ['name' => 'b', 'validation' => ['required', 'email']],
            ['name' => 'c', 'validation' => ['string']],
            ['name' => 'd'],
            ['name' => 'e', 'validation' => 'sometimes|string'],
        ]]);

        [$rules] = $definition->rules();

        expect($rules)->toBe([
            'a' => ['required', 'string'],
            'b' => ['required', 'email'],
            'c' => ['nullable', 'string'],
            'd' => ['nullable'],
            'e' => ['sometimes', 'string'],
        ])->and(array_column($definition->fields(), 'required', 'name'))->toBe(['a' => true, 'b' => true, 'c' => false, 'd' => false, 'e' => false]);
    });

    it('reads only the camelCase keys', function (): void {
        $definition = new FormDefinition([
            'key' => 'zz',
            'send_email' => true,
            'sendConfirmation' => true,
            'confirmationField' => 'reply_to',
            'fields' => [['name' => 'x', 'error_messages' => ['required' => 'old'], 'errorMessages' => ['email' => 'new']]],
        ]);

        expect($definition->columns())->toBe(['send_confirmation' => true, 'confirmation_field' => 'reply_to'])
            ->and($definition->rules()[1])->toBe(['x.email' => 'new'])
            ->and($definition->outdatedKeys())->toBe(['send_email', 'error_messages']);
    });
});

it('keeps a UTF-8 BOM and the text after it', function (): void {
    $source = "\xEF\xBB\xBFkey: zz-bom\nsuccess_message: 'Danke'\n";

    expect(app(FormYamlMigrator::class)->migrateSource($source))->toBe("\xEF\xBB\xBFkey: zz-bom\nsuccessMessage: 'Danke'\n");
});
