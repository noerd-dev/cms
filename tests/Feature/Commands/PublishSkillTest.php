<?php

use Noerd\Cms\Commands\CmsInstallCommand;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    $this->skillsDir = base_path('.claude/skills');
    $this->target = $this->skillsDir . '/cms-website-import';
    $this->backup = $this->target . '.zz-test-backup';

    if (! is_dir($this->skillsDir)) {
        mkdir($this->skillsDir, 0755, true);
    }

    // Snapshot a pre-existing entry (file, symlink or directory) so every test
    // runs against a clean target; afterEach always restores the snapshot.
    if (is_link($this->target) || file_exists($this->target)) {
        rename($this->target, $this->backup);
    }
});

afterEach(function (): void {
    // Always clean up whatever the test produced, then restore the snapshot.
    if (is_link($this->target) || is_file($this->target)) {
        @unlink($this->target);
    } elseif (is_dir($this->target)) {
        zzCmsRemoveDirectory($this->target);
    }

    if (is_link($this->backup) || file_exists($this->backup)) {
        rename($this->backup, $this->target);
    }
});

function zzCmsRemoveDirectory(string $path): void
{
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $path . '/' . $entry;
        if (is_dir($full) && ! is_link($full)) {
            zzCmsRemoveDirectory($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($path);
}

function zzCmsInvokePublishSkills(bool $refreshCopies): void
{
    $command = app(CmsInstallCommand::class);
    $command->setLaravel(app());
    $command->setOutput(new \Illuminate\Console\OutputStyle(
        new \Symfony\Component\Console\Input\ArrayInput([]),
        new \Symfony\Component\Console\Output\NullOutput(),
    ));

    $reflection = new ReflectionMethod($command, 'publishSkills');
    $reflection->setAccessible(true);
    $reflection->invoke($command, $refreshCopies);
}

it('auto-discovers and publishes the cms-website-import skill', function (): void {
    zzCmsInvokePublishSkills(refreshCopies: false);

    expect(is_link($this->target) || is_dir($this->target))->toBeTrue();
    expect(file_exists($this->target . '/SKILL.md'))->toBeTrue();
});

it('leaves an existing symlink alone on update', function (): void {
    // Deterministic symlink scenario: place the link ourselves instead of
    // depending on how the first publish materialized it in this environment.
    symlink('../../app-modules/cms/skills/cms-website-import', $this->target);

    $linkTargetBefore = readlink($this->target);

    zzCmsInvokePublishSkills(refreshCopies: true);

    expect(is_link($this->target))->toBeTrue();
    expect(readlink($this->target))->toEqual($linkTargetBefore);
});

it('refreshes a stale copied skill on update', function (): void {
    mkdir($this->target, 0755, true);
    file_put_contents($this->target . '/SKILL.md', 'STALE');

    zzCmsInvokePublishSkills(refreshCopies: true);

    expect(is_link($this->target) || is_dir($this->target))->toBeTrue();
    expect(file_exists($this->target . '/SKILL.md'))->toBeTrue();
    expect(file_get_contents($this->target . '/SKILL.md'))->not->toEqual('STALE');
});
