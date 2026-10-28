<?php

namespace App\Http\Controllers\Admin;

use App\Support\Audit\AuditLog;
use App\Support\Backups\Backups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Admin → Backups. See App\Support\Backups\Backups for what a backup is and
 * where the copies go.
 *
 * The server has no Terminal, so this page is how the owner takes a backup
 * before a risky change and how they get a copy onto their own computer.
 */
class BackupsController extends Controller
{
    public function __construct(protected Backups $backups, protected AuditLog $audit) {}

    public function index(): Response
    {
        return response()->view('admin.backups.index', [
            'activeNav' => 'backups',
            'files' => $this->backups->list(),
            'last' => $this->backups->last(),
            'keepLocal' => Backups::KEEP_LOCAL,
            'keepDrive' => Backups::KEEP_DRIVE,
        ]);
    }

    /**
     * POST /admin/backups - take one now.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $status = $this->backups->run();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.backups')
                ->with('status', 'The backup failed: '.$e->getMessage())
                ->with('status_tone', 'danger');
        }

        $this->audit->record(
            action: AuditLog::BACKUP_TAKEN,
            actor: $request->user(),
            entityType: 'backup',
            entityId: $status['file'],
            after: $status['tables'].' tables, Drive: '.$status['drive'],
            request: $request,
        );

        $sent = $status['drive'] === 'sent';

        return redirect()->route('admin.backups')
            ->with('status', 'Backup taken.'.($sent
                ? ' A copy is in the Shared Drive.'
                : ' It was NOT copied to Google Drive ('.$status['drive'].'), so it exists on this server only.'))
            ->with('status_tone', $sent ? 'success' : 'warning');
    }

    /**
     * GET /admin/backups/{file} - download one.
     */
    public function download(Request $request, string $file): BinaryFileResponse
    {
        abort_unless(Backups::isBackupName($file), 404);

        $path = Backups::directory().'/'.$file;

        abort_unless(is_file($path), 404);

        $this->audit->record(
            action: AuditLog::BACKUP_DOWNLOADED,
            actor: $request->user(),
            entityType: 'backup',
            entityId: $file,
            request: $request,
        );

        return response()->download($path, $file, ['Content-Type' => 'application/gzip']);
    }
}
