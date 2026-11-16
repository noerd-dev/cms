<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Illuminate\Console\Command;
use Noerd\Cms\Forms\FormYamlMigrator;
use Noerd\Support\ModulesPath;

/**
 * Rewrites the project's form YAML (`app-configs/cms/forms/*.yml`) to the 0.6
 * vocabulary (FormYamlMigrator). `noerd:update-cms` runs it on every update;
 * this command is the way to look first (`--dry-run`).
 */
class MigrateFormYamlCommand extends Command
{
    protected $signature = 'cms:migrate-form-yaml
                            {--dry-run : Only list the files that would change, write nothing}';

    protected $description = 'Rewrite the CMS form YAML to the current (camelCase) vocabulary';

    /**
     * The project's forms and — for the module living in the project's
     * modules path (the development monorepo) — the module's own copies.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        $paths = [base_path('app-configs/cms/forms')];
        $moduleForms = dirname(__DIR__, 2) . '/app-configs/cms/forms';
        $modulesRoot = realpath(ModulesPath::absolute());
        $moduleRoot = realpath(dirname(__DIR__, 2));

        if (is_dir($moduleForms) && $modulesRoot !== false && $moduleRoot !== false
            && str_starts_with($moduleRoot, $modulesRoot . DIRECTORY_SEPARATOR)) {
            $paths[] = $moduleForms;
        }

        return $paths;
    }

    public function handle(FormYamlMigrator $migrator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = $migrator->migrate(self::paths(), $dryRun);

        if ($changed === []) {
            $this->info('Every form YAML file already uses the current vocabulary.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            $dryRun ? '<comment>%d form YAML file(s) would be updated:</comment>' : '<info>Updated %d form YAML file(s):</info>',
            count($changed),
        ));

        foreach ($changed as $file) {
            $this->line('  ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file));
        }

        return self::SUCCESS;
    }
}
