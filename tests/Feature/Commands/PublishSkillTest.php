<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Cms\Commands\CmsInstallCommand;

uses(Tests\TestCase::class);

/*
 | publishSkills() writes into base_path('.claude/skills'). The tests therefore
 | run against a THROWAWAY base path: the host's own .claude directory (and any
 | symlink an installation placed there) is never touched.
 */
beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-cms-skills');

    File::deleteDirectory($this->hostPath);
    File::ensureDirectoryExists($this->hostPath . '/.claude/skills');

    $this->app->setBasePath($this->hostPath);

    $this->skillsDir = base_path('.claude/skills');
    $this->target = $this->skillsDir . '/cms-website-import';
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
});

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
    symlink(dirname(__DIR__, 3) . '/skills/cms-website-import', $this->target);

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
