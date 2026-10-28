<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Database changes waiting to be applied. See Admin\UpdatesController.
 */
class Updates
{
    /**
     * Pending migration names, oldest first. Empty when up to date - and
     * empty, not an exception, if the migrations table cannot be read, so a
     * broken database never takes the Admin Panel down with it.
     *
     * @return list<string>
     */
    public static function pending(): array
    {
        try {
            /** @var Migrator $migrator */
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return [];
            }

            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran = $migrator->getRepository()->getRan();

            return array_values(array_diff(array_keys($files), $ran));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * A migration's file name as something readable:
     * `2026_10_27_000001_add_ctc_to_employee_salary_structures`
     * becomes "Add ctc to employee salary structures".
     */
    public static function describe(string $migration): string
    {
        return ucfirst(str_replace('_', ' ', (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migration)));
    }
}
