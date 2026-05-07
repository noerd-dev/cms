<?php

use Noerd\Cms\Commands\NoerdCmsInstallCommand;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    $this->skillsDir = base_path('.claude/skills');
    $this->target = $this->skillsDir . '/cms-website-import';

    if (is_link($this->target) || file_exists($this->target)) {
        $this->preExisting = true;
    } else {
        $this->preExisting = false;
    }
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

it('publishes the cms-website-import skill into .claude/skills', function (): void {
    if ($this->preExisting) {
        $this->markTestSkipped('Skill already published in this project; skipping side-effect test.');
    }

    $command = app(NoerdCmsInstallCommand::class);
    $command->setLaravel(app());
    $command->setOutput(new \Illuminate\Console\OutputStyle(
        new \Symfony\Component\Console\Input\ArrayInput([]),
        new \Symfony\Component\Console\Output\NullOutput(),
    ));

    $reflection = new ReflectionMethod($command, 'publishSkill');
    $reflection->setAccessible(true);
    $reflection->invoke($command);

    expect(is_link($this->target) || is_dir($this->target))->toBeTrue();
    expect(file_exists($this->target . '/SKILL.md'))->toBeTrue();
});

it('skips publishing when the skill already exists', function (): void {
    if (! $this->preExisting) {
        $command = app(NoerdCmsInstallCommand::class);
        $command->setLaravel(app());
        $command->setOutput(new \Illuminate\Console\OutputStyle(
        new \Symfony\Component\Console\Input\ArrayInput([]),
        new \Symfony\Component\Console\Output\NullOutput(),
    ));

        $reflection = new ReflectionMethod($command, 'publishSkill');
        $reflection->setAccessible(true);
        $reflection->invoke($command);
    }

    $beforeMtime = filemtime($this->target);
    clearstatcache();

    $command = app(NoerdCmsInstallCommand::class);
    $command->setLaravel(app());
    $command->setOutput(new \Illuminate\Console\OutputStyle(
        new \Symfony\Component\Console\Input\ArrayInput([]),
        new \Symfony\Component\Console\Output\NullOutput(),
    ));

    $reflection = new ReflectionMethod($command, 'publishSkill');
    $reflection->setAccessible(true);
    $reflection->invoke($command);

    expect(filemtime($this->target))->toEqual($beforeMtime);
});
