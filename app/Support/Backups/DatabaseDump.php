<?php

namespace App\Support\Backups;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * The whole database as a gzipped .sql file, written in PHP.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY NOT mysqldump
 *
 * The live server is shared cPanel hosting with no Terminal, where `exec()`
 * and `proc_open()` are commonly disabled and the mysqldump path is not ours
 * to know. Reading the tables through the connection the application already
 * has works anywhere the application works. The database is small (a
 * six-person company), so streaming rows in chunks is quick.
 *
 * The output is ordinary SQL - DROP/CREATE then INSERTs - and restores with
 * phpMyAdmin's Import tab. Foreign key checks are switched off for the
 * duration so tables can load in any order.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Sensitive columns that the application encrypts stay encrypted in the dump,
 * so a restore needs the same APP_KEY. That is also why the key must never
 * change on the server.
 */
class DatabaseDump
{
    protected const CHUNK = 500;

    /**
     * Write the dump to `$path` (gzipped). Returns the number of tables.
     *
     * @throws RuntimeException
     */
    public function write(string $path): int
    {
        $pdo = DB::connection()->getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $gz = gzopen($path, 'wb6');

        if ($gz === false) {
            throw new RuntimeException('Could not open '.$path.' for writing.');
        }

        try {
            gzwrite($gz, '-- '.config('zephryx.brand.name').' CRM database backup'."\n");
            gzwrite($gz, '-- Taken '.now()->toDateTimeString().' ('.config('app.timezone').")\n");
            gzwrite($gz, "-- Restore with phpMyAdmin > Import. Needs the same APP_KEY as the server.\n\n");

            if ($driver === 'mysql') {
                gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
            }

            $tables = $this->tables($pdo, $driver);

            foreach ($tables as $table) {
                gzwrite($gz, $this->createStatement($pdo, $driver, $table));
                $this->writeRows($gz, $pdo, $driver, $table);
            }

            if ($driver === 'mysql') {
                gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
            }

            return count($tables);
        } finally {
            gzclose($gz);
        }
    }

    /**
     * @return list<string>
     */
    protected function tables(PDO $pdo, string $driver): array
    {
        $rows = $driver === 'mysql'
            ? $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM)
            // SQLite, for the test suite.
            : $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_NUM);

        return array_map(fn (array $row) => (string) $row[0], $rows);
    }

    protected function createStatement(PDO $pdo, string $driver, string $table): string
    {
        $quoted = $this->quoteName($table, $driver);

        if ($driver === 'mysql') {
            $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
        } else {
            $statement = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
            $statement->execute([$table]);
            $create = (string) $statement->fetchColumn();
        }

        return "-- ── {$table}\nDROP TABLE IF EXISTS {$quoted};\n{$create};\n\n";
    }

    /**
     * @param  resource  $gz
     */
    protected function writeRows($gz, PDO $pdo, string $driver, string $table): void
    {
        $quoted = $this->quoteName($table, $driver);
        $offset = 0;

        do {
            $rows = $pdo->query('SELECT * FROM '.$quoted.' LIMIT '.self::CHUNK.' OFFSET '.$offset)
                ->fetchAll(PDO::FETCH_ASSOC);

            if ($rows === []) {
                break;
            }

            $columns = implode(', ', array_map(fn ($c) => $this->quoteName($c, $driver), array_keys($rows[0])));
            $values = array_map(
                fn (array $row) => '('.implode(', ', array_map(fn ($v) => $this->literal($pdo, $v), $row)).')',
                $rows,
            );

            gzwrite($gz, 'INSERT INTO '.$quoted.' ('.$columns.") VALUES\n".implode(",\n", $values).";\n");

            $offset += self::CHUNK;
        } while (count($rows) === self::CHUNK);

        gzwrite($gz, "\n");
    }

    protected function literal(PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => $pdo->quote((string) $value),
        };
    }

    protected function quoteName(string $name, string $driver): string
    {
        return $driver === 'mysql'
            ? '`'.str_replace('`', '``', $name).'`'
            : '"'.str_replace('"', '""', $name).'"';
    }
}
