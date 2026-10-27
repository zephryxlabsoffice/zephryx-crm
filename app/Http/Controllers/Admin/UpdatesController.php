<?php

namespace App\Http\Controllers\Admin;

use App\Support\Audit\AuditLog;
use App\Support\Backups\Backups;
use App\Support\Updates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Admin → Updates: apply database changes without a Terminal (2026-10-27).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS PAGE EXISTS
 *
 * The live server is cPanel with no Terminal, so `php artisan migrate` cannot
 * be typed. After uploading a version that adds a table or a column, the owner
 * opens this page and presses one button. It takes a backup FIRST — a
 * half-applied change with no way back is the failure this exists to avoid —
 * then runs the pending migrations and shows what happened.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class UpdatesController extends Controller
{
    public function __construct(protected Backups $backups, protected AuditLog $audit) {}

    public function index(): Response
    {
        return response()->view('admin.updates.index', [
            'activeNav' => 'updates',
            'pending' => Updates::pending(),
        ]);
    }

    /**
     * POST /admin/updates — back up, then apply.
     */
    public function store(Request $request): RedirectResponse
    {
        $pending = Updates::pending();

        if ($pending === []) {
            return redirect()->route('admin.updates')
                ->with('status', 'Nothing to apply — the database is up to date.')
                ->with('status_tone', 'info');
        }

        try {
            $backup = $this->backups->run();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.updates')
                ->with('status', 'Nothing was changed: the backup before the update failed ('.$e->getMessage().').')
                ->with('status_tone', 'danger');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            report($e);

            $this->audit->record(
                action: AuditLog::UPDATE_FAILED,
                actor: $request->user(),
                entityType: 'update',
                entityId: $backup['file'],
                after: $e->getMessage(),
                request: $request,
            );

            return redirect()->route('admin.updates')
                ->with('status', 'The update failed: '.$e->getMessage().' A backup was taken just before ('
                    .$backup['file'].') — restore it from Admin → Backups if anything looks wrong.')
                ->with('status_tone', 'danger');
        }

        $this->audit->record(
            action: AuditLog::UPDATE_APPLIED,
            actor: $request->user(),
            entityType: 'update',
            entityId: $backup['file'],
            after: 'Applied: '.implode(', ', $pending),
            request: $request,
        );

        return redirect()->route('admin.updates')
            ->with('status', 'Update applied ('.count($pending).' change'.(count($pending) === 1 ? '' : 's').'). '
                .'A backup was taken first: '.$backup['file'].'.')
            ->with('status_tone', 'success')
            ->with('update_output', $output);
    }
}
