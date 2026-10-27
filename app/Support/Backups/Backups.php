<?php

namespace App\Support\Backups;

use App\Models\GoogleConnection;
use App\Support\Google\DriveClient;
use App\Support\Google\GoogleAuth;
use App\Support\Google\GoogleServiceAccountKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Database backups: take one, keep a few, send a copy off the server.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO COPIES, IN TWO PLACES (decided 2026-10-27)
 *
 * 1. On the server, in storage/app/backups — the last KEEP_LOCAL days. Quick
 *    to download from Admin → Backups, but it lives on the same disk as the
 *    database, so it does not survive losing the server.
 * 2. In the Google Shared Drive, in a "backups" folder — the last KEEP_DRIVE.
 *    The copy that survives the server, the hosting account, or both.
 *
 * Run daily by a cPanel Cron Job (`php artisan backup:database`), and on
 * demand from the Admin Panel. A Drive failure does not fail the backup: the
 * local copy is still good, and the status says the off-site half is missing.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class Backups
{
    public const KEEP_LOCAL = 14;

    public const KEEP_DRIVE = 60;

    public const DRIVE_FOLDER = 'backups';

    protected const PREFIX = 'zephryx-crm-db-';

    public function __construct(protected DatabaseDump $dump) {}

    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * Take a backup now.
     *
     * @return array{file: string, bytes: int, tables: int, drive: string, at: string}
     */
    public function run(): array
    {
        File::ensureDirectoryExists(self::directory());

        // The folder must never be web-reachable; storage/ is outside public/,
        // and this is belt and braces for a host that serves it anyway.
        if (! File::exists(self::directory().'/.htaccess')) {
            File::put(self::directory().'/.htaccess', "Require all denied\nDeny from all\n");
        }

        $name = self::PREFIX.now()->format('Y-m-d-His').'.sql.gz';
        $path = self::directory().'/'.$name;

        $tables = $this->dump->write($path);

        $this->pruneLocal();

        $drive = $this->sendToDrive($name, $path);

        $status = [
            'file' => $name,
            'bytes' => (int) filesize($path),
            'tables' => $tables,
            'drive' => $drive,
            'at' => now()->toIso8601String(),
        ];

        File::put(self::directory().'/last.json', json_encode($status, JSON_PRETTY_PRINT));

        return $status;
    }

    /**
     * The backups on the server, newest first.
     *
     * @return list<array{name: string, bytes: int, at: Carbon}>
     */
    public function list(): array
    {
        if (! File::isDirectory(self::directory())) {
            return [];
        }

        $files = [];

        foreach (File::files(self::directory()) as $file) {
            if (! self::isBackupName($file->getFilename())) {
                continue;
            }

            $files[] = [
                'name' => $file->getFilename(),
                'bytes' => $file->getSize(),
                // The office's timezone, explicitly: Carbon 3 reads a Unix
                // timestamp as UTC, which showed backups 5h30m early.
                'at' => Carbon::createFromTimestamp($file->getMTime(), config('app.timezone')),
            ];
        }

        usort($files, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $files;
    }

    /**
     * What the last run said, or null if there has never been one.
     *
     * @return array<string, mixed>|null
     */
    public function last(): ?array
    {
        $path = self::directory().'/last.json';

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * A name is only ever one we wrote. This is what stands between the
     * download route and a path like "../../.env".
     */
    public static function isBackupName(string $name): bool
    {
        return preg_match('/^'.preg_quote(self::PREFIX, '/').'\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/', $name) === 1;
    }

    protected function pruneLocal(): void
    {
        foreach (array_slice($this->list(), self::KEEP_LOCAL) as $old) {
            File::delete(self::directory().'/'.$old['name']);
        }
    }

    /**
     * Upload to the Shared Drive and trim the folder there.
     *
     * @return string 'sent', 'not connected', or the error
     */
    protected function sendToDrive(string $name, string $path): string
    {
        $connection = GoogleConnection::current();

        if (! $connection->isConnected()) {
            return 'not connected';
        }

        try {
            $drive = new DriveClient(
                new GoogleAuth(GoogleServiceAccountKey::parse($connection->service_account_key)),
                (string) $connection->shared_drive_id,
            );

            $drive->upload(self::DRIVE_FOLDER, $name, (string) File::get($path), 'application/gzip');

            $copies = array_values(array_filter(
                $drive->listFolder(self::DRIVE_FOLDER),
                fn (array $f) => self::isBackupName($f['name']),
            ));

            foreach (array_slice($copies, self::KEEP_DRIVE) as $old) {
                $drive->delete($old['id']);
            }

            return 'sent';
        } catch (Throwable $e) {
            Log::error('Backup could not be sent to Google Drive: '.$e->getMessage());

            return 'failed: '.$e->getMessage();
        }
    }
}
