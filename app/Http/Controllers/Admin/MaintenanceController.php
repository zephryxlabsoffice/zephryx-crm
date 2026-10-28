<?php

namespace App\Http\Controllers\Admin;

use App\Mail\MaintenanceMail;
use App\Support\Audit\AuditLog;
use App\Support\Maintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Admin → Maintenance. See App\Support\Maintenance for what it does.
 *
 * Each action switches first, audits, and only then emails: the switch is the
 * point and the email is the courtesy, so a mail server that refuses leaves the
 * change made and says the emails did not go.
 */
class MaintenanceController extends Controller
{
    public function __construct(protected AuditLog $audit) {}

    public function index(): Response
    {
        return response()->view('admin.maintenance.index', [
            'activeNav' => 'maintenance',
            'state' => Maintenance::state(),
            'active' => Maintenance::isActive(),
        ]);
    }

    /**
     * POST /admin/maintenance/now
     */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);

        $state = Maintenance::startNow($request->user(), $data['note'] ?? null);

        $this->record($request, AuditLog::MAINTENANCE_STARTED, 'now', 'Site closed now');

        return $this->tell($request, MaintenanceMail::STARTED, $state,
            'Maintenance is on. Only the Admin Panel works until you turn it off.');
    }

    /**
     * POST /admin/maintenance/schedule
     */
    public function schedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date', 'after:now'],
            'to' => ['required', 'date', 'after:from'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);

        $state = Maintenance::schedule($from, $to, $request->user(), $data['note'] ?? null);

        $this->record($request, AuditLog::MAINTENANCE_SCHEDULED, 'scheduled',
            $from->format('d M Y H:i').' to '.$to->format('d M Y H:i'));

        return $this->tell($request, MaintenanceMail::SCHEDULED, $state,
            'Maintenance is scheduled from '.$from->format('D j M, g:i A').' to '.$to->format('D j M, g:i A').'.');
    }

    /**
     * POST /admin/maintenance/end - turn it off, or call off a schedule.
     */
    public function end(Request $request): RedirectResponse
    {
        $state = Maintenance::end();

        if ($state === null) {
            return redirect()->route('admin.maintenance')
                ->with('status', 'Maintenance was already off.')
                ->with('status_tone', 'info');
        }

        $wasActive = $state['was_active'];

        $this->record($request, AuditLog::MAINTENANCE_ENDED, $wasActive ? 'ended' : 'cancelled',
            $wasActive ? 'Site open again' : 'Schedule called off');

        return $this->tell($request, $wasActive ? MaintenanceMail::ENDED : MaintenanceMail::CANCELLED, $state,
            $wasActive ? 'Maintenance is off and the site is open again.' : 'The scheduled maintenance is called off.');
    }

    protected function record(Request $request, string $action, string $id, string $after): void
    {
        $this->audit->record(
            action: $action,
            actor: $request->user(),
            entityType: 'maintenance',
            entityId: $id,
            after: $after,
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function tell(Request $request, string $kind, array $state, string $done): RedirectResponse
    {
        try {
            $sent = Maintenance::tell($kind, $state);
            $message = $done.' '.$sent.' '.($sent === 1 ? 'person was' : 'people were').' emailed.';
            $tone = 'success';
        } catch (Throwable $e) {
            report($e);
            $message = $done.' But the emails could not be sent: '.$e->getMessage();
            $tone = 'warning';
        }

        return redirect()->route('admin.maintenance')
            ->with('status', $message)
            ->with('status_tone', $tone);
    }
}
