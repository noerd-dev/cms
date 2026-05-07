<?php

use Noerd\Cms\Commands\NoerdCmsInstallCommand;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    $this->skillsDir = base_path('.claude/skills');
    $this->target = $this->skillsDir . '/cms-website-import';

    $this->preExisting = is_link($this->target) || file_exists($this->target);
});

afterEach(function (): void {
    if (! ($this->preExisting ?? false) && (is_link($this->target) || file_exists($this->target))) {
        if (is_link($this->target) || is_file($this->target)) {
            @unlink($this->target);
        } elseif (is_dir($this->target)) {
            removeDirectory($this->target);
        }
    }
});

function removeDirectory(string $path): void
{
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $path . '/' . $entry;
        if (is_dir($full) && ! is_link($full)) {
            removeDirectory($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($path);
}

function invokePublishSkills(bool $refreshCopies): void
{
    $command = app(NoerdCmsInstallCommand::class);
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
    if ($this->preExisting) {
        $this->markTestSkipped('Skill already published in this project; skipping side-effect test.');
    }

    invokePublishSkills(refreshCopies: false);

    expect(is_link($this->target) || is_dir($this->target))->toBeTrue();
    expect(file_exists($this->target . '/SKILL.md'))->toBeTrue();
});

it('leaves an existing symlink alone on update', function (): void {
    if (! $this->preExisting) {
        invokePublishSkills(refreshCopies: false);
    }

    if (! is_link($this->target)) {
        $this->markTestSkipped('Skill is not a symlink in this environment; refresh-symlink behavior cannot be asserted.');
    }

    $linkTargetBefore = readlink($this->target);

    invokePublishSkills(refreshCopies: true);

    expect(is_link($this->target))->toBeTrue();
    expect(readlink($this->target))->toEqual($linkTargetBefore);
});

it('refreshes a stale copied skill on update', function (): void {
    if ($this->preExisting) {
        $this->markTestSkipped('Skill pre-existing; cannot safely overwrite for refresh test.');
    }

    if (! is_dir($this->skillsDir) && ! mkdir($this->skillsDir, 0755, true)) {
        $this->markTestSkipped('Could not create .claude/skills directory.');
    }

    mkdir($this->target, 0755, true);
    file_put_contents($this->target . '/SKILL.md', 'STALE');

    invokePublishSkills(refreshCopies: true);

    expect(is_link($this->target) || is_dir($this->target))->toBeTrue();
    expect(file_exists($this->target . '/SKILL.md'))->toBeTrue();
    expect(file_get_contents($this->target . '/SKILL.md'))->not->toEqual('STALE');
});
