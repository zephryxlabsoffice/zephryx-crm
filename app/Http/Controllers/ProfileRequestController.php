<?php

namespace App\Http\Controllers;

use App\Models\ProfileChangeRequest;
use App\Support\Audit\AuditLog;
use App\Support\Profile\ProfileChanges;
use App\Support\Rbac\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * HR's side of the change-request flow (2026-09-14).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A QUEUE, BECAUSE THE ALTERNATIVE IS A RECORD NOBODY OPENS
 *
 * The requests could have lived only on each employee's record page, which
 * would have been less code. It would also mean HR discovering a request by
 * happening to open the right person — so somebody who brought their documents
 * in on Monday waits until the next time anybody looks at their record. A
 * pending change with no queue is a pending change forever.
 *
 * Oldest first, and the count is on the Employees page, so the work is visible
 * from where HR already is.
 *
 * WHY `employees.edit` AND NOT A NEW PERMISSION
 *
 * Applying one of these IS editing an employment record — the same act as
 * typing the correction into the employee form, arrived at from the other
 * side. A separate key would be a second answer to a question §5 has already
 * answered, and the two would drift.
 *
 * NOBODY DECIDES THEIR OWN
 *
 * The same rule as nobody approving their own leave and nobody closing their
 * own record (§2.6). Otherwise the whole flow is a formality: an HR person
 * could submit a change to their own address and accept it in the next click,
 * which is precisely the "moves on assertion" the reversal was about.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ProfileRequestController extends Controller
{
    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected ProfileChanges $changes,
    ) {
    }

    /**
     * GET /employees/requests
     */
    public function index(Request $request): Response
    {
        $queue = $this->changes->queue();

        $rows = $queue->map(fn (ProfileChangeRequest $item) => [
            'request' => $item,
            'staff_id' => $item->employee?->user?->user_id,
            'name' => $item->employee?->user?->name,
            /*
             * Field labels, never values. A queue is a list read over somebody's
             * shoulder in an open-plan office, and "Sunita asked to change her
             * address" is all it has to say to be useful — the address itself is
             * on the page you have to open on purpose.
             */
            'asks' => $this->changes->summarise($item),
            'mine' => $item->employee?->user_id === $request->user()->id,
        ])->all();

        return response()->view('employees.requests.index', [
            'activeNav' => 'employees',
            'rows' => $rows,
        ]);
    }

    /**
     * GET /employees/requests/{request}
     */
    public function show(Request $request, ProfileChangeRequest $profileRequest): Response
    {
        return response()->view('employees.requests.show', [
            'activeNav' => 'employees',
            'request' => $profileRequest,
            'staffId' => $profileRequest->employee?->user?->user_id,
            'name' => $profileRequest->employee?->user?->name,
            // Before and after, built in PHP — see ProfileChanges::comparison.
            'rows' => $this->changes->comparison($profileRequest),
            'hasPhoto' => $profileRequest->photo_path !== null,
            'mine' => $profileRequest->employee?->user_id === $request->user()->id,
        ]);
    }

    /**
     * POST /employees/requests/{request}/apply
     */
    public function apply(Request $request, ProfileChangeRequest $profileRequest): RedirectResponse
    {
        $this->guard($request, $profileRequest);

        $applied = $this->changes->apply($profileRequest, $request->user());

        $this->audit->record(
            action: AuditLog::PROFILE_CHANGE_APPLIED,
            actor: $request->user(),
            /*
             * Filed against the PERSON, not against HR. The question this
             * answers months later is "what happened to my record", and an
             * entry under the approver answers a different one.
             */
            entityType: 'user',
            entityId: $profileRequest->employee?->user?->user_id ?? 'unknown',
            after: 'Applied: '.implode(', ', $applied),
            request: $request,
        );

        return redirect()
            ->route('employees.requests.index')
            ->with('status', 'Applied to '.($profileRequest->employee?->user?->name ?? 'the record').'.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /employees/requests/{request}/reject
     */
    public function reject(Request $request, ProfileChangeRequest $profileRequest): RedirectResponse
    {
        $this->guard($request, $profileRequest);

        $data = $request->validate([
            /*
             * Required, and the reason is the same as the identifier reveal's:
             * the person reads this, possibly weeks later. "Declined" on its
             * own tells them nothing and sends them to ask somebody — which is
             * the conversation this field exists to save.
             */
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        $summary = $this->changes->summarise($profileRequest);

        $this->changes->reject($profileRequest, $request->user(), $data['reason']);

        $this->audit->record(
            action: AuditLog::PROFILE_CHANGE_REJECTED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $profileRequest->employee?->user?->user_id ?? 'unknown',
            // The fields asked for and the stated reason. Never the values.
            after: 'Declined: '.implode(', ', $summary).' — '.$data['reason'],
            request: $request,
        );

        return redirect()
            ->route('employees.requests.index')
            ->with('status', 'Declined, and they have been told why.')
            ->with('status_tone', 'info');
    }

    /**
     * Two checks, and neither is about the permission — the route did that.
     */
    protected function guard(Request $request, ProfileChangeRequest $profileRequest): void
    {
        // Already decided. Two people opening the queue at once is ordinary,
        // and the second one must not overwrite the first's decision.
        abort_if(! $profileRequest->isPending(), 404);

        // Nobody decides their own. See the head of this class.
        abort_if($profileRequest->employee?->user_id === $request->user()->id, 403);
    }
}
