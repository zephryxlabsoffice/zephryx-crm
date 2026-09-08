<?php

namespace App\Http\Controllers;

use App\Mail\AccountInviteMail;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\PasswordResets;
use App\Support\EmployeeDirectory;
use App\Support\EmployeePresenter;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Employees — the spine every other module references (foundation spec §12).
 *
 * Reads the `employees` table through App\Support\EmployeeDirectory, which owns
 * the row shape the views expect. Filtering and pagination happen in SQL: the
 * page this replaced loaded every employee and filtered the collection in PHP,
 * which is survivable at twelve people and a full table scan per keystroke at
 * any size worth having a search box for.
 */
class EmployeeController extends Controller
{
    protected const PER_PAGE = 8;

    /** The donut's radius and the circumference derived from it. */
    protected const DONUT_RADIUS = 57;

    public function __construct(protected Rbac $rbac, protected AuditLog $audit)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(EmployeePresenter::statusOptions())],
            'department' => ['nullable', 'string', 'max:60'],
        ]);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;
        $department = $filters['department'] ?? null;

        $query = EmployeeDirectory::query(
            $search !== '' ? $search : null,
            $status,
            $department,
        );

        return response()->view('employees.index', [
            'activeNav' => 'employees',
            'employees' => EmployeeDirectory::paginate($query, self::PER_PAGE),
            'search' => $search,
            'status' => $status,
            'department' => $department,
            'filtered' => $search !== '' || $status !== null || $department !== null,
            'departments' => EmployeeDirectory::departmentsInUse(),
            'stats' => EmployeeDirectory::stats(),
            'breakdown' => EmployeeDirectory::byDepartment(),
            'circumference' => 2 * M_PI * self::DONUT_RADIUS,
            'donutRadius' => self::DONUT_RADIUS,
            'starters' => EmployeeDirectory::recentStarters(),
            'birthdays' => EmployeeDirectory::birthdays(),
            // Drawn once here rather than asked per row: the table renders an
            // action menu on every line, and a permission check inside the loop
            // is the same answer computed twelve times.
            'mayEdit' => $this->rbac->can($request->user(), 'employees.edit'),
            'mayCreate' => $this->rbac->can($request->user(), 'employees.create'),
        ]);
    }

    public function show(Request $request, string $employee): Response
    {
        $record = $this->find($employee);

        return response()->view('employees.show', [
            'activeNav' => 'employees',
            'employee' => EmployeeDirectory::row($record),
            'record' => $record,
            'history' => $this->audit->entriesFor('employee', $employee),
            'mayEdit' => $this->rbac->can($request->user(), 'employees.edit'),
            'mayDeactivate' => $this->rbac->can($request->user(), 'employees.deactivate')
                // Nobody closes their own record. The same rule as nobody
                // approving their own leave, for the same reason: a control
                // somebody can apply to themselves is not a control (§2.6).
                && $record->user_id !== $request->user()->id,
        ]);
    }

    public function create(Request $request): Response
    {
        return response()->view('employees.form', [
            'activeNav' => 'employees',
            'employee' => null,
            'staffId' => $this->nextStaffId(),
        ] + $this->formOptions());
    }

    /**
     * Add somebody.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS CREATES AN ACCOUNT, AND THAT IS THE POINT
     *
     * §1 says there is no public sign-up and every account is created by an
     * administrator. This is that act — which is why `employees.create` is a
     * sensitive permission and why the whole thing is one transaction: an
     * account with no employment record is a person who can sign in and has no
     * department, and an employment record with no account is a row nobody can
     * ever log in as.
     *
     * NOBODY TYPES SOMEBODY ELSE'S PASSWORD
     *
     * The account is created with a random one nobody sees, and the person sets
     * their own through a single-use link. HR choosing a password would mean HR
     * knowing it, and "temporary" passwords are shared over chat, reused, and
     * never actually changed.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $employee = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'user_id' => $this->nextStaffId(),
                'name' => $data['name'],
                'email' => $data['email'],
                // Never used. Overwritten the moment they follow the link, and
                // long enough that it cannot be guessed in the meantime.
                'password' => Str::random(64),
                'account_type' => Realm::STAFF,
                // The Employee base comes from this column and not from a role,
                // so no role edit can take somebody's own attendance and
                // payslips away from them (§2.2).
                'staff_kind' => 'employee',
                'status' => 'active',
            ]);

            $user->roles()->sync(Role::where('role_key', 'employee')->pluck('id'));

            return Employee::create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'joined_on' => $data['joined_on'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'announce_milestones' => $data['announce_milestones'] ?? true,
            ]);
        });

        $this->invite($employee, $request);

        $this->audit->record(
            action: AuditLog::EMPLOYEE_CREATED,
            actor: $request->user(),
            entityType: 'employee',
            entityId: $employee->user->user_id,
            after: $employee->user->name.' added as '.($employee->designation?->name ?? 'staff')
                .' in '.($employee->department?->name ?? 'no department'),
            request: $request,
        );

        return redirect()
            ->route('employees.show', ['employee' => $employee->user->user_id])
            ->with('status', $employee->user->name.' was added. They have been emailed a link to set their password.')
            ->with('status_tone', 'success');
    }

    public function edit(Request $request, string $employee): Response
    {
        $record = $this->find($employee);

        return response()->view('employees.form', [
            'activeNav' => 'employees',
            'employee' => $record,
            'staffId' => $record->user->user_id,
        ] + $this->formOptions());
    }

    public function update(Request $request, string $employee): RedirectResponse
    {
        $record = $this->find($employee);
        $data = $this->validated($request, $record);

        $before = $this->describe($record);

        DB::transaction(function () use ($record, $data) {
            $record->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $record->update([
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'joined_on' => $data['joined_on'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'announce_milestones' => $data['announce_milestones'] ?? false,
            ]);
        });

        $record->refresh()->load(['user', 'department', 'designation']);

        $this->audit->record(
            action: AuditLog::EMPLOYEE_UPDATED,
            actor: $request->user(),
            entityType: 'employee',
            entityId: $record->user->user_id,
            before: $before,
            after: $this->describe($record),
            request: $request,
        );

        return redirect()
            ->route('employees.show', ['employee' => $record->user->user_id])
            ->with('status', 'Record updated.')
            ->with('status_tone', 'success');
    }

    /**
     * Close or reopen somebody's record.
     *
     * There is no delete. Attendance, payroll and the audit log all point back
     * at an employee, so removing the row would either orphan years of records
     * or take them with it. Deactivating stops the account signing in and
     * leaves everything that happened intact.
     */
    public function status(Request $request, string $employee): RedirectResponse
    {
        $record = $this->find($employee);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        if ($record->user_id === $request->user()->id) {
            // Belt and braces: the button is not rendered for your own record
            // either. A control somebody can apply to themselves is not one.
            throw ValidationException::withMessages([
                'status' => 'You cannot change the status of your own record.',
            ]);
        }

        /*
         * Rank, not role (§2.5). HR outranks a Manager in `people` and may
         * close their record; a Manager holding employees.deactivate may not
         * close HR's. The permission says what somebody may do, and this says
         * who they may do it to.
         */
        if (! $this->rbac->outranks($request->user(), $record->user, 'people')) {
            abort(403);
        }

        $before = $record->user->status;

        $record->user->update(['status' => $data['status']]);

        /*
         * Everything they hold is re-resolved from scratch. An inactive account
         * holds no permissions at all (Rbac rule 2), and a cached answer from
         * earlier in this request would say otherwise.
         */
        $this->rbac->forget($record->user);

        $this->audit->record(
            action: AuditLog::EMPLOYEE_STATUS_CHANGED,
            actor: $request->user(),
            entityType: 'employee',
            entityId: $record->user->user_id,
            before: $before,
            after: $data['status'],
            request: $request,
        );

        return redirect()
            ->route('employees.show', ['employee' => $record->user->user_id])
            ->with('status', $record->user->name.' is now '.$data['status'].'.')
            ->with('status_tone', $data['status'] === 'active' ? 'success' : 'info');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function find(string $staffId): Employee
    {
        return Employee::query()
            ->with(['user', 'department', 'designation'])
            ->whereHas('user', fn ($q) => $q->where('user_id', $staffId))
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Employee $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],

            /*
             * Unique across every account, not just employees. The email is the
             * sign-in identifier (§4.1), so a client and an employee sharing one
             * would be two accounts one credential could mean.
             */
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($existing?->user_id),
            ],

            // Master data, and it must be a row from the RIGHT list — otherwise
            // a crafted id could set somebody's department to a leave type.
            'department_id' => [
                'nullable',
                Rule::exists('master_data_items', 'id')
                    ->where('list', MasterDataItem::DEPARTMENTS)
                    ->where('is_active', true),
            ],
            'designation_id' => [
                'nullable',
                Rule::exists('master_data_items', 'id')
                    ->where('list', MasterDataItem::DESIGNATIONS)
                    ->where('is_active', true),
            ],

            'joined_on' => ['required', 'date', 'before_or_equal:today'],

            /*
             * A birthday in the future is a typo, and so is one implying
             * somebody is 12. Bounded rather than merely `date`, because this
             * feeds the announcements board and a wrong one is published to
             * everybody.
             */
            'date_of_birth' => ['nullable', 'date', 'before:-15 years', 'after:-100 years'],

            'announce_milestones' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * The dropdowns.
     *
     * Only ACTIVE master data: a deactivated department keeps the people
     * already in it and stops being offered for new ones, which is the whole
     * difference between deactivating and deleting.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'departments' => MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->active()->get(),
            'designations' => MasterDataItem::inList(MasterDataItem::DESIGNATIONS)->active()->get(),
        ];
    }

    /**
     * The next EMP number.
     *
     * Derived from the highest existing one rather than from a count, because a
     * count reissues an id the moment anybody is removed — and an id that has
     * belonged to two people is one that makes an audit trail ambiguous.
     */
    protected function nextStaffId(): string
    {
        $highest = User::query()
            ->where('account_type', Realm::STAFF)
            ->where('user_id', 'like', 'EMP%')
            ->selectRaw('max(cast(substr(user_id, 4) as integer)) as n')
            ->value('n');

        return 'EMP'.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Send the link that lets somebody set their first password.
     */
    protected function invite(Employee $employee, Request $request): void
    {
        $token = app(PasswordResets::class)->issue($employee->user, $request);

        Mail::to($employee->user->email)->send(new AccountInviteMail(
            url: route('password.reset.form', ['token' => $token]),
            name: $employee->user->name,
            staffId: $employee->user->user_id,
            addedBy: $request->user()->name,
        ));
    }

    /**
     * One line describing a record, for the audit log's before and after.
     */
    protected function describe(Employee $employee): string
    {
        return implode(' · ', array_filter([
            $employee->user->name,
            $employee->user->email,
            $employee->department?->name,
            $employee->designation?->name,
            $employee->joined_on?->format('d M Y'),
        ]));
    }
}
