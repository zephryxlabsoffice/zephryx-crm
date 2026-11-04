<?php

use App\Support\AnnouncementDelivery;
use App\Support\Backups\Backups;
use App\Support\Launch;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The daily database backup (2026-10-27). Run by a cPanel Cron Job once a
 * day - see docs/DEPLOY.md. Also available as Admin → Backups → Back up now.
 * Exit code 1 on failure so cPanel's cron email reports it.
 */
Artisan::command('backup:database', function (Backups $backups) {
    // Also the only timer for scheduled announcements. First, and on its own, so a
    // failed backup does not also stop people being told. See AnnouncementDelivery.
    AnnouncementDelivery::deliverDueQuietly();

    try {
        $status = $backups->run();
    } catch (Throwable $e) {
        $this->error('Backup failed: '.$e->getMessage());

        return 1;
    }

    $this->info('Backup taken: '.$status['file'].' ('.$status['tables'].' tables, '
        .number_format($status['bytes'] / 1024, 1).' KB). Google Drive: '.$status['drive'].'.');

    // The only job on a timer, so it also sends the launch-day welcome at
    // 2 AM on the day (once). See App\Support\Launch.
    if (($welcomed = Launch::sendWelcomeIfDue()) > 0) {
        $this->info('Launch day: welcome emailed to '.$welcomed.' people.');
    }

    return $status['drive'] === 'sent' || $status['drive'] === 'not connected' ? 0 : 1;
})->purpose('Back up the database to storage/app/backups and the Google Shared Drive');
