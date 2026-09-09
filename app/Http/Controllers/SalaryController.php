<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryRecord;
use App\Support\Audit\AuditLog;
use App\Support\Documents\DocumentStore;
use App\Support\EmployeeDirectory;
use App\Support\Money;
use App\Support\Rbac\Rbac;
use App\Support\SalaryDirectory;
use App\Support\SalaryPresenter;
use App\Support\Sensitive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Salary — recording payslips and tracking who has been paid.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS APPLICATION DOES NOT CALCULATE PAYROLL
 *
 * Decided 2026-08-27. Pay is worked out in Excel today and will come from
 * payroll software over an API later. Here, somebody adds the payslip that
 * calculation produced, types the net figure it states, and marks the transfer
 * done. Nothing is derived.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * AND IT IS STILL THE MOST SENSITIVE MODULE IN THE APPLICATION
 *
 * 1. `salary.view` IS ITS OWN SENSITIVE PERMISSION. Seeing what a colleague
 *    earns is itself the harm; it is not implied by `employees.view`.
 *
 * 2. THE OWN-PAYSLIP ROUTE TAKES NO EMPLOYEE. `/salary/payslip/{period}`
 *    resolves the person from the session, so there is no identifier to tamper
 *    with and no ownership check anybody can forget to write. The management
 *    view of somebody else's record is a separate, separately guarded route.
 *
 * 3. IDENTIFIERS ARE MASKED IN PHP, AND ONLY ON YOUR OWN RECORD. Nothing
 *    unmasked reaches a view — not even to somebody who may see the record —
 *    because "•••• 4567" is a display and the real value is a credential.
 *
 * 4. MARKING PAID IS A FINANCIAL WRITE. Single or bulk, it is audited, and it
 *    is idempotent: marking an already-paid record does not move its date.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class SalaryController extends Controller
{
    protected const PER_PAGE = 12;

    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected DocumentStore $documents,
    ) {
    }

    /**
     * GET /salary — the month's payroll.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $period = $filters['period'];

        $records = SalaryDirectory::forPeriod($period);

        return response()->view('salary.index', [
            'activeNav' => 'salary',
            'records' => $this->paginate($this->matching($records, $filters), $request),
            'stats' => SalaryDirectory::stats($records),
            'periods' => SalaryPresenter::periodOptions(),
            'departments' => EmployeeDirectory::departmentsInUse(),
            'missing' => SalaryDirectory::missingFrom($period),
            'withoutBanking' => SalaryDirectory::withoutBanking(),
        ] + $filters);
    }

    /**
     * GET /salary/mine — the signed-in person's own pay.
     */
    public function mine(Request $request): Response
    {
        $viewer = $this->employeeFor($request);

        return response()->view('salary.mine', [
            'activeNav' => 'salary',
            'employee' => $viewer ? EmployeeDirectory::row($viewer) : null,
            'latest' => SalaryDirectory::latestFor($viewer),
            'history' => SalaryDirectory::forEmployee($viewer),
            // Masked here, in PHP. The unmasked values never enter the view.
            'identity' => $this->maskedIdentity($viewer, $viewer),
            // The way back to payroll, for the people who could have come from
            // it.
            'canViewPayroll' => $this->rbac->can($request->user(), 'salary.view'),
        ]);
    }

    /**
     * GET /salary/payslip/{period} — the viewer's own payslip for a month.
     *
     * No employee parameter by design; see the class comment.
     */
    public function payslip(Request $request, string $period): Response
    {
        abort_if(! $this->isPeriod($period), 404);

        $viewer = $this->employeeFor($request);
        abort_if($viewer === null, 404);

        $record = SalaryDirectory::find($period, (string) $viewer->user?->user_id);

        abort_if($record === null, 404);

        return response()->view('salary.record', [
            'activeNav' => 'salary',
            'record' => $record,
            'own' => true,
            'identity' => $this->maskedIdentity($viewer, $viewer),
            'mayManage' => false,
        ]);
    }

    /**
     * GET /salary/{employee}/{period} — one person's record, for whoever runs
     * payroll. This is where a payslip is added and the transfer marked done.
     */
    public function show(Request $request, string $employee, string $period): Response
    {
        abort_if(! $this->isPeriod($period), 404);

        $record = SalaryDirectory::find($period, $employee);

        abort_if($record === null, 404);

        $viewer = $this->employeeFor($request);
        $subject = $this->employeeByStaffId($employee);
        $own = $viewer !== null && $subject !== null && $viewer->id === $subject->id;

        return response()->view('salary.record', [
            'activeNav' => 'salary',
            'record' => $record,
            'own' => $own,
            /*
             * Not the viewer's own record, so nothing sensitive is passed at
             * all — not even a masked string, because "•••• 4567" still
             * confirms an account exists.
             */
            'identity' => $this->maskedIdentity($viewer, $subject),
            'mayManage' => $this->rbac->can($request->user(), 'salary.manage'),
        ]);
    }

    /**
     * POST /salary/pay/confirm — name who is about to be marked paid.
     *
     * A read, not a write: it renders the list and asks. Marking twelve people
     * paid by mis-click is a hard thing to notice and an awkward thing to undo,
     * so the destructive step is always the second one.
     *
     * POST rather than GET so a dozen employee ids do not end up in a URL that
     * gets bookmarked, shared or logged.
     */
    public function confirmPayment(Request $request): Response
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'employees' => ['required', 'array', 'min:1'],
            'employees.*' => ['string', 'max:32'],
        ]);

        $records = SalaryDirectory::forPeriod($validated['period'])
            ->whereIn('employee', $validated['employees'])
            // Only what can actually be paid. A record with no payslip, or one
            // already paid, is silently dropped here rather than being carried
            // into a confirmation that promises something it cannot do.
            ->filter(fn (array $r) => SalaryPresenter::isPayable($r))
            ->values();

        return response()->view('salary.confirm-payment', [
            'activeNav' => 'salary',
            'period' => $validated['period'],
            'records' => $records,
            // The gap between what was ticked and what can be paid, stated
            // rather than swallowed.
            'skipped' => count($validated['employees']) - $records->count(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE WRITES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Add the payslip for one person's month, and the figure it states.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE NET IS TYPED, NOT DERIVED — AND IT IS TYPED IN RUPEES
     *
     * It is stored in paise as an integer, converted once here. Money is never
     * a float in this application: 0.1 + 0.2 is not 0.3 in binary floating
     * point, and somebody's pay is the least forgivable place for that.
     *
     * The file goes to a private disk and is served only through the download
     * route below. It is never written to the webroot.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function storePayslip(Request $request, string $employee, string $period): RedirectResponse
    {
        abort_if(! $this->isPeriod($period), 404);

        $subject = $this->employeeByStaffId($employee);
        abort_if($subject === null, 404);

        /*
         * Nobody runs payroll on themselves. The same rule as nobody approving
         * their own leave and nobody rejecting their own attendance: a control
         * somebody can apply to themselves is not a control, and this is the
         * one where it means setting your own pay.
         */
        abort_if($this->employeeFor($request)?->id === $subject->id, 403);

        $data = $request->validate([
            'net' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'payslip' => [
                'required', 'file',
                'mimes:'.implode(',', DocumentStore::ALLOWED),
                'max:'.(int) (DocumentStore::MAX_BYTES / 1024),
            ],
        ]);

        $record = SalaryRecord::firstOrNew([
            'employee_id' => $subject->id,
            'period' => $period,
        ]);

        if ($record->paid_on !== null) {
            /*
             * A paid month is closed. Replacing the payslip on a record the
             * money has already left against would mean the document and the
             * transfer disagree, and the transfer is the one that happened.
             */
            throw ValidationException::withMessages([
                'payslip' => 'This month has already been paid. A correction has to be made in payroll and added as the next month\'s adjustment.',
            ]);
        }

        // What the record said before, for the audit entry. A replacement is
        // the case where "what was the figure before" is the whole question.
        $before = $record->exists && $record->net() !== null
            ? 'net '.$record->net()->format()
            : null;

        $stored = $this->documents->put('payslips/'.$period, $request->file('payslip'));

        // A replaced payslip's file goes. Nothing points at it any more, and
        // keeping somebody's pay document with no record naming it is worse
        // than deleting it.
        $this->documents->forget($record->payslip_path);

        $record->fill([
            'net_minor' => Money::fromMajor($data['net'])->minor,
            'currency' => Money::DEFAULT_CURRENCY,
            'payslip_path' => $stored['path'],
            'payslip_name' => $stored['name'],
            'payslip_bytes' => $stored['bytes'],
            'payslip_added_at' => now(),
            'payslip_added_by' => $this->employeeFor($request)?->id,
        ])->save();

        $this->audit->record(
            action: AuditLog::SALARY_PAYSLIP_ADDED,
            actor: $request->user(),
            entityType: 'salary',
            entityId: $period.'-'.$employee,
            before: $before,
            /*
             * The FIGURE is in the entry and the document is not. "Who set this
             * month's net to what" is the question somebody asks of a payroll
             * log, and it cannot be answered by a filename.
             */
            after: ($before === null ? 'Payslip added' : 'Payslip replaced').' · net '.$record->net()->format(),
            request: $request,
        );

        return redirect()
            ->route('salary.show', ['employee' => $employee, 'period' => $period])
            ->with('status', 'Payslip added.')
            ->with('status_tone', 'success');
    }

    /**
     * Mark people paid.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * IDEMPOTENT, AND ONE ENTRY PER PERSON
     *
     * A record already paid is skipped rather than re-stamped: the date it
     * carries is when the money actually moved, and moving it because somebody
     * pressed the button twice would make the payroll trail describe the button
     * rather than the transfer.
     *
     * The audit entry is per person, not per batch. "When was Amit paid for
     * September" has to be answerable without reading a list of twelve names
     * out of one entry's payload.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function pay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'employees' => ['required', 'array', 'min:1'],
            'employees.*' => ['string', 'max:32'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $paidOn = $data['paid_on'] ?? Carbon::today()->toDateString();
        $actor = $this->employeeFor($request);

        $records = SalaryRecord::query()
            ->with('employee.user')
            ->where('period', $data['period'])
            // Already paid, or no payslip: skipped rather than re-stamped. The
            // date a record carries is when the money actually moved.
            ->payable()
            ->whereHas('employee.user', fn ($q) => $q->whereIn('user_id', $data['employees']))
            // Nobody marks themselves paid, even inside a batch of twelve.
            ->when($actor, fn ($q) => $q->where('employee_id', '!=', $actor->id))
            ->get();

        foreach ($records as $record) {
            $record->update([
                'paid_on' => $paidOn,
                'paid_by' => $actor?->id,
            ]);

            $this->audit->record(
                action: AuditLog::SALARY_PAID,
                actor: $request->user(),
                entityType: 'salary',
                entityId: $record->period.'-'.$record->employee?->user?->user_id,
                after: 'Paid '.$record->net()?->format().' on '.Carbon::parse($paidOn)->format('d M Y'),
                request: $request,
            );
        }

        $count = $records->count();
        $skipped = count($data['employees']) - $count;

        return redirect()
            ->route('salary.index', ['period' => $data['period']])
            ->with('status', $count.' '.($count === 1 ? 'person was' : 'people were').' marked paid.'
                .($skipped > 0 ? ' '.$skipped.' had already been paid or had no payslip.' : ''))
            ->with('status_tone', 'success');
    }

    /**
     * Hand somebody a payslip file.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE ONE WAY A STORED FILE LEAVES THIS APPLICATION
     *
     * Never a static path. This checks who is asking — your own payslip, or
     * somebody holding `salary.view` — and writes an audit entry saying they
     * took it, before a single byte is streamed. §6 asks for exactly this, and
     * the reason is that a payslip under a guessable public URL is a link
     * somebody can forward.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function downloadPayslip(Request $request, string $employee, string $period): StreamedResponse
    {
        abort_if(! $this->isPeriod($period), 404);

        $subject = $this->employeeByStaffId($employee);
        abort_if($subject === null, 404);

        $viewer = $this->employeeFor($request);
        $own = $viewer !== null && $viewer->id === $subject->id;

        abort_unless($own || $this->rbac->can($request->user(), 'salary.view'), 403);

        $record = SalaryRecord::where('employee_id', $subject->id)
            ->where('period', $period)
            ->firstOrFail();

        abort_if($record->payslip_path === null || ! $this->documents->exists($record->payslip_path), 404);

        $this->audit->record(
            action: AuditLog::SALARY_PAYSLIP_DOWNLOADED,
            actor: $request->user(),
            entityType: 'salary',
            entityId: $period.'-'.$employee,
            after: $own ? 'Downloaded their own payslip' : 'Downloaded the payslip',
            request: $request,
        );

        return $this->documents->download($record->payslip_path, $record->payslip_name ?? 'payslip.pdf');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::with('user')->where('user_id', $request->user()?->id)->first();
    }

    protected function employeeByStaffId(string $staffId): ?Employee
    {
        return Employee::query()
            ->with(['user', 'department', 'designation'])
            ->whereHas('user', fn ($q) => $q->where('user_id', $staffId))
            ->first();
    }

    /**
     * Mask a record's identifiers — or withhold them entirely.
     *
     * @return array<string, string>|null
     */
    protected function maskedIdentity(?Employee $viewer, ?Employee $subject): ?array
    {
        if ($viewer === null || $subject === null) {
            return null;
        }

        if (! Sensitive::viewerMaySee($viewer->user?->user_id, (string) $subject->user?->user_id)) {
            return null;
        }

        $banking = SalaryDirectory::banked($subject);

        if ($banking === null) {
            return null;
        }

        return [
            'bank' => $banking['bank'],
            'ifsc' => Sensitive::ifsc($banking['ifsc']),
            'account' => Sensitive::accountNumber($banking['account']),
            'pan' => Sensitive::pan($banking['pan']),
            'aadhaar' => Sensitive::aadhaar($banking['aadhaar']),
        ];
    }

    protected function isPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(SalaryPresenter::statusOptions())],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'period' => $validated['period'] ?? SalaryDirectory::currentPeriod(),
            'search' => $search,
            'department' => $validated['department'] ?? null,
            'status' => $validated['status'] ?? null,
            'filtered' => $search !== ''
                || ($validated['department'] ?? null) !== null
                || ($validated['status'] ?? null) !== null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $records, array $filters): Collection
    {
        return $records
            ->when($filters['search'] !== '', fn (Collection $rows) => $rows->filter(
                fn (array $r) => str_contains(
                    mb_strtolower($r['employee_record']['name'].' '.$r['employee']),
                    mb_strtolower($filters['search'])
                )
            ))
            ->when($filters['department'], fn (Collection $rows) => $rows->filter(
                fn (array $r) => $r['employee_record']['department'] === $filters['department']
            ))
            ->when($filters['status'], fn (Collection $rows) => $rows->filter(
                fn (array $r) => SalaryPresenter::statusOf($r) === $filters['status']
            ))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, self::PER_PAGE)->values(),
            total: $rows->count(),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
