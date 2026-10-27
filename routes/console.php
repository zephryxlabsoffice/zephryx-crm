<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The daily database backup (2026-10-27). Run by a cPanel Cron Job once a
 * day — see docs/DEPLOY.md. Also available as Admin → Backups → Back up now.
 * Exit code 1 on failure so cPanel's cron email reports it.
 */
Artisan::command('backup:database', function (App\Support\Backups\Backups $backups) {
    try {
        $status = $backups->run();
    } catch (Throwable $e) {
        $this->error('Backup failed: '.$e->getMessage());

        return 1;
    }

    $this->info('Backup taken: '.$status['file'].' ('.$status['tables'].' tables, '
        .number_format($status['bytes'] / 1024, 1).' KB). Google Drive: '.$status['drive'].'.');

    return $status['drive'] === 'sent' || $status['drive'] === 'not connected' ? 0 : 1;
})->purpose('Back up the database to storage/app/backups and the Google Shared Drive');
