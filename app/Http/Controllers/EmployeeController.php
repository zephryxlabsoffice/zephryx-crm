<?php

namespace App\Http\Controllers;

use App\Mail\AccountInviteMail;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\PasswordResets;
use App\Models\EmployeeBanking;
use App\Models\EmployeeProfile;
use App\Support\EmployeeDirectory;
use App\Support\EmployeePresenter;
use App\Support\IdProof;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use App\Support\SalaryDirectory;
use App\Support\Sensitive;
use App\Support\StaffId;
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

    /**
     * The three things a reveal may ask for, and what each is called.
     *
     * A fixed list because the key arrives in the request and names a column.
     * IFSC is absent on purpose: it is never masked, so there is nothing to
     * reveal — and the bank name is not an identifier at all.
     *
     * @var array<string, string>
     */
    protected const REVEALABLE = [
        'id_proof_number' => 'ID proof number',
        'pan' => 'PAN',
        'account_number' => 'Account number',
    ];

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
            /*
             * Masked here, in PHP, before anything reaches the template. The
             * view never receives a full number to hide — markup the browser
             * was sent has already been read by whoever is sitting at it.
             */
            'identity' => $this->withReveal($this->maskedIdentity($request, $record), $record),
            /*
             * Behind the same permission as the identity card and, unlike it,
             * shown in full. An address is not a credential — masking it would
             * protect nothing and stop HR spotting the typo that sends a
             * courier to the wrong street — but it is still nobody's business
             * who merely holds `employees.view`.
             */
            'addresses' => $this->addresses($request, $record),
            'maySeeIdentifiers' => $this->rbac->can($request->user(), 'employees.identifiers'),
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
            /*
             * No preview. The identifier carries the engagement type as a
             * digit, so it is not known until the type is chosen — and a
             * preview that silently went stale when somebody changed the
             * dropdown would be worse than saying it is assigned on save.
             */
            'staffId' => null,
            // Nothing on file for somebody who does not exist yet.
            'identity' => null,
            'addresses' => null,
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
                'user_id' => StaffId::forEmployee($data['employment_type']),
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

            $employee = Employee::create([
                'user_id' => $user->id,
                // The column, not the digit in the identifier. The digit was
                // true on the day it was issued; this is what every rule that
                // turns on the engagement type actually reads.
                'employment_type' => $data['employment_type'],
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'joined_on' => $data['joined_on'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'announce_milestones' => $data['announce_milestones'] ?? true,
            ]);

            // Inside the same transaction: an account with no identity record
            // when the form demanded one is a half-made hire.
            $this->writeIdentity($employee, $data, $request);
            $this->writeAddresses($employee, $data, $request);

            return $employee;
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
            /*
             * Masked, as a hint beside an EMPTY input. The form never receives
             * a real number, so there is nothing for it to post back — which is
             * what stops a saved mask overwriting somebody's actual Aadhaar
             * with `XXXX XXXX 1234`.
             */
            'identity' => $this->maskedIdentity($request, $record),
            /*
             * Prefilled, unlike the identity fields beside them: there is
             * nothing to hide from somebody who may already read the card, and
             * an address is corrected a line at a time rather than retyped. It
             * is still null without the permission, and the form then treats an
             * empty box as "keep what is stored".
             */
            'addresses' => $this->addresses($request, $record),
        ] + $this->formOptions());
    }

    public function update(Request $request, string $employee): RedirectResponse
    {
        $record = $this->find($employee);
        $data = $this->validated($request, $record);

        $before = $this->describe($record);

        DB::transaction(function () use ($record, $data, $request) {
            $record->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $this->writeIdentity($record, $data, $request);
            $this->writeAddresses($record, $data, $request);

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

    /**
     * Show one identifier in full, once, and write down that it happened.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE FIELD NAME COMES FROM THE REQUEST AND READS A COLUMN
     *
     * So it is checked against a fixed list rather than trusted. Unchecked,
     * `password` and `remember_token` would be exactly as valid a thing to ask
     * for as `pan`, and the reveal would become a read-anything endpoint that
     * happens to be called reveal.
     *
     * THE REASON IS REQUIRED BECAUSE THE ENTRY IS READ MONTHS LATER
     *
     * "HR viewed an account number on 14 September" answers nothing on its own.
     * "…because a transfer bounced and the bank asked us to confirm it" is what
     * makes the entry worth having, and nobody will remember it afterwards.
     */
    public function reveal(Request $request, string $employee): RedirectResponse
    {
        $record = $this->find($employee);

        $data = $request->validate([
            'field' => ['required', Rule::in(array_keys(self::REVEALABLE))],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        $banking = EmployeeBanking::where('employee_id', $record->id)->first();

        // Nothing on file is not a reveal that failed — there was no look to
        // record, so nothing is written either.
        if ($banking === null) {
            abort(404);
        }

        $field = $data['field'];
        $label = self::REVEALABLE[$field];

        $this->audit->record(
            action: AuditLog::IDENTIFIER_REVEALED,
            actor: $request->user(),
            entityType: 'employee',
            entityId: $record->user?->user_id ?? (string) $record->id,
            // The field and the stated reason. Never the value — see the
            // constant's own note in App\Support\Audit\AuditLog.
            after: $label.' — '.$data['reason'],
            request: $request,
        );

        return redirect()
            ->route('employees.show', ['employee' => $record->user?->user_id])
            /*
             * Flashed, so it survives exactly one render. Anything longer-lived
             * would be a page that shows a full identifier to whoever opens it
             * next, which is the state this whole module is shaped to avoid.
             */
            ->with('revealed', [
                'staff_id' => $record->user?->user_id,
                'field' => $field,
                'label' => $label,
                'value' => (string) $banking->{$field},
            ]);
    }

    /**
     * Fold a just-revealed value into the masked card, for this render only.
     *
     * Done HERE and not in `maskedIdentity()`, which the edit form also calls:
     * a reveal must never reach a page whose inputs post back what they are
     * shown, or the next save would write the revealed value into a field the
     * form is supposed to leave alone.
     *
     * @param  array<string, mixed>|null  $identity
     * @return array<string, mixed>|null
     */
    protected function withReveal(?array $identity, Employee $record): ?array
    {
        $revealed = session('revealed');

        if ($identity === null || ! is_array($revealed)) {
            return $identity;
        }

        // The flash names whose record it belongs to. Without this check a
        // reveal on one person would light up the same field on the next
        // record the viewer opened.
        if (($revealed['staff_id'] ?? null) !== $record->user?->user_id) {
            return $identity;
        }

        return $identity + [
            'revealed_field' => $revealed['field'],
            'revealed_label' => $revealed['label'],
            'revealed_value' => $revealed['value'],
        ];
    }

    /**
     * Somebody else's identifiers, masked — or nothing at all.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE QUERY IS THE GATE, NOT THE TEMPLATE
     *
     * Without the permission this returns null and the row is never loaded, so
     * there is nothing for a markup mistake to leak. A card rendered inside an
     * `@if` around values the controller fetched anyway is one refactor away
     * from being a card rendered outside it.
     *
     * Returns null for "may not see" and an array with `on_file => false` for
     * "may see, and there is nothing there" — two different facts. The second
     * is the one that matters operationally: somebody nobody has set up cannot
     * be paid, and that has to be visible as an absence rather than as a
     * section that quietly does not appear.
     *
     * @return array<string, mixed>|null
     */
    protected function maskedIdentity(Request $request, Employee $record): ?array
    {
        if (! $this->rbac->can($request->user(), 'employees.identifiers')) {
            return null;
        }

        $banking = SalaryDirectory::banked($record);

        if ($banking === null) {
            return ['on_file' => false];
        }

        return [
            'on_file' => true,
            // The type itself is not sensitive — which document somebody
            // produced is not the half worth protecting — and the edit form
            // needs it to preselect the dropdown.
            'type' => $banking['id_proof_type'],
            'id_proof_label' => $banking['id_proof_label'],
            'id_proof' => Sensitive::idProof($banking['id_proof_type'], $banking['id_proof_number']),
            'copy_received_on' => $banking['id_proof_copy_received_on'],
            'pan' => Sensitive::pan($banking['pan']),
            'bank' => $banking['bank'],
            'account' => Sensitive::accountNumber($banking['account']),
            // Unmasked on purpose: it identifies a branch, not a person, and is
            // printed on every cheque.
            'ifsc' => Sensitive::ifsc($banking['ifsc']),
        ];
    }

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
        $rules = [
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
        ];

        /*
         * ─────────────────────────────────────────────────────────────────────
         * THE ENGAGEMENT TYPE IS SET WHEN SOMEBODY IS ADDED, AND NEVER EDITED
         *
         * An intern becoming full-time is a conversion: a new identifier is
         * issued, the old record is closed, and the leave balance starts
         * again. Allowing the same change through the edit form would do none
         * of that — it would leave a `ZEPH262001` whose column says full-time,
         * with a year of intern leave behind it and no audit entry saying when
         * it changed.
         *
         * So the rule exists only when creating. On an edit the field is not
         * accepted at all, rather than accepted and ignored.
         * ─────────────────────────────────────────────────────────────────────
         */
        if ($existing === null) {
            $rules['employment_type'] = ['required', Rule::in(Employee::TYPES)];
        }

        return $request->validate(
            $rules + $this->identityRules($request, $existing) + $this->addressRules($request, $existing),
            $this->identityMessages($request),
        );
    }

    /**
     * Identity documents and bank details.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * REQUIRED WHEN HIRING, OPTIONAL WHEN CORRECTING
     *
     * On a CREATE these are required, because the owner decided a record is not
     * complete without them (2026-09-12) — with one exception: an intern is not
     * required to have a PAN, because students frequently do not have one and
     * refusing would mean the intern cannot be added at all. A freelancer must,
     * since they invoice us and that is a TDS matter.
     *
     * On an EDIT every one of them is optional, and that is not laxness — it is
     * the whole mechanism. The form is rendered with EMPTY inputs beside masked
     * hints, because filling them would put the real numbers into the HTML. So
     * an empty field means "leave what is stored alone", and Laravel's
     * conversion of empty strings to null is what makes `nullable` say exactly
     * that. A field that is filled in is still checked against its document's
     * shape.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array<string, list<mixed>>
     */
    protected function identityRules(Request $request, ?Employee $existing): array
    {
        $creating = $existing === null;
        $required = $creating ? 'required' : 'nullable';

        // The intern exception, and only on a create — an edit demands nothing.
        $panRequired = $creating && $request->input('employment_type') !== Employee::INTERN
            ? 'required'
            : 'nullable';

        return [
            'id_proof_type' => [$creating ? 'required' : 'nullable', Rule::in(array_keys(IdProof::TYPES))],
            'id_proof_number' => array_merge(
                [$required, 'string'],
                IdProof::numberRules((string) $request->input('id_proof_type')),
            ),
            // The photocopy arrives on paper. Null means nobody has it yet,
            // which is the state HR has to be able to chase.
            'id_proof_copy_received_on' => ['nullable', 'date', 'before_or_equal:today'],

            // Five letters, four digits, a check letter.
            'pan' => [$panRequired, 'string', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],

            'bank_name' => [$required, 'string', 'max:120'],
            // Four letters, then a zero, then six more. The zero is the
            // character people get wrong, and a wrong IFSC is a failed transfer.
            'ifsc' => [$required, 'string', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            // Indian account numbers run nine to eighteen digits.
            'account_number' => [$required, 'string', 'regex:/^[0-9]{9,18}$/'],
        ];
    }

    /**
     * Messages a person can act on, rather than ones naming a regex.
     *
     * @return array<string, string>
     */
    protected function identityMessages(Request $request): array
    {
        return [
            'id_proof_number.regex' => IdProof::formatMessage((string) $request->input('id_proof_type')),
            'pan.regex' => 'A PAN is five letters, four digits and a letter — like ABCDE1234F.',
            'ifsc.regex' => 'An IFSC is four letters, a zero, then six characters — like HDFC0001234.',
            'account_number.regex' => 'An account number is 9 to 18 digits, with no spaces.',
        ];
    }

    /**
     * Write the identity record, and record THAT it changed.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ONLY WHAT WAS ACTUALLY TYPED IS WRITTEN
     *
     * Every null is skipped rather than saved. On an edit that is what "blank
     * keeps" means; on a create it simply means the field was optional and not
     * supplied.
     *
     * THE AUDIT ENTRY NAMES FIELDS, NEVER VALUES
     *
     * Where somebody's pay lands is worth a permanent record of who changed it
     * and when. The new account number is not: writing it here would copy the
     * value into the one table this application refuses to let anybody edit,
     * and the audit screen lists that table by the page.
     *
     * @param  array<string, mixed>  $data
     */
    protected function writeIdentity(Employee $employee, array $data, Request $request): void
    {
        $columns = [
            'id_proof_type' => 'ID proof',
            'id_proof_number' => 'ID proof number',
            'id_proof_copy_received_on' => 'Photocopy received',
            'pan' => 'PAN',
            'bank_name' => 'Bank',
            'ifsc' => 'IFSC',
            'account_number' => 'Account number',
        ];

        $supplied = [];

        foreach (array_keys($columns) as $column) {
            $value = $data[$column] ?? null;

            if ($value !== null && $value !== '') {
                $supplied[$column] = $value;
            }
        }

        if ($supplied === []) {
            return;
        }

        $existing = EmployeeBanking::where('employee_id', $employee->id)->first();

        $changed = [];

        foreach ($supplied as $column => $value) {
            if ($existing === null || (string) $existing->{$column} !== (string) $value) {
                $changed[] = $columns[$column];
            }
        }

        EmployeeBanking::updateOrCreate(['employee_id' => $employee->id], $supplied);

        if ($changed === []) {
            return;
        }

        $this->audit->record(
            action: AuditLog::SALARY_BANKING_CHANGED,
            actor: $request->user(),
            entityType: 'employee',
            entityId: $employee->user?->user_id ?? (string) $employee->id,
            after: ($existing === null ? 'Recorded: ' : 'Changed: ').implode(', ', $changed),
            request: $request,
        );
    }

    /**
     * Where somebody lives, and the address on the document they were checked
     * against.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * REQUIRED FOR A FULL-TIME HIRE, OPTIONAL FOR EVERYBODY ELSE
     *
     * The owner's list (2026-09-12) is: full-time everything; an intern ID
     * proof, phone and bank; a freelancer those plus a PAN. An address is in
     * nobody's list but the first, so it is demanded there and offered
     * everywhere else. A freelancer we pay against an invoice does not need to
     * tell us which flat they work from.
     *
     * On an EDIT both are optional, and empty means keep — the same rule the
     * identity fields follow, so one form has one behaviour rather than two.
     * The boxes ARE prefilled here (see `addresses()`), so an empty one is
     * either a deliberate clear-out by somebody who can see what they cleared,
     * or an editor without the permission who was shown nothing to begin with.
     * Keeping is the safe reading of both.
     *
     * @return array<string, list<string>>
     */
    protected function addressRules(Request $request, ?Employee $existing): array
    {
        $required = $existing === null
            && $request->input('employment_type') === Employee::FULL_TIME
            ? 'required'
            : 'nullable';

        return [
            'current_address' => [$required, 'string', 'max:500'],
            'permanent_address' => [$required, 'string', 'max:500'],
        ];
    }

    /**
     * The two addresses on file, or null for somebody who may not read them.
     *
     * Behind `employees.identifiers` — the permission the directory does not
     * carry — and, unlike the identifiers themselves, returned in full. Masking
     * an address would protect nothing a mask can protect and would stop HR
     * seeing the typo that sends a courier to the wrong street.
     *
     * @return array{current: ?string, permanent: ?string}|null
     */
    protected function addresses(Request $request, Employee $record): ?array
    {
        if (! $this->rbac->can($request->user(), 'employees.identifiers')) {
            return null;
        }

        $profile = $record->profile;

        return [
            'current' => $profile?->current_address,
            'permanent' => $profile?->permanent_address,
        ];
    }

    /**
     * Write the addresses, and record THAT they changed.
     *
     * Blank keeps, exactly as `writeIdentity()` does, and for the same reason:
     * one form, one rule about what an empty box means. The audit entry names
     * the fields and never the values — see the constant's own note.
     *
     * @param  array<string, mixed>  $data
     */
    protected function writeAddresses(Employee $employee, array $data, Request $request): void
    {
        $columns = [
            'current_address' => 'Current address',
            'permanent_address' => 'Permanent address',
        ];

        $supplied = [];

        foreach (array_keys($columns) as $column) {
            $value = $data[$column] ?? null;

            if ($value !== null && trim($value) !== '') {
                $supplied[$column] = trim($value);
            }
        }

        if ($supplied === []) {
            return;
        }

        $existing = EmployeeProfile::where('employee_id', $employee->id)->first();

        $changed = [];

        foreach ($supplied as $column => $value) {
            if ($existing === null || (string) $existing->{$column} !== $value) {
                $changed[] = $columns[$column];
            }
        }

        /*
         * The profile row is created here if the person has never opened their
         * own profile page. That is not the same as the page creating one: the
         * distinction the table draws is "not stated" versus "stated as
         * nothing", and HR typing an address is a statement.
         */
        EmployeeProfile::updateOrCreate(['employee_id' => $employee->id], $supplied);

        if ($changed === []) {
            return;
        }

        $this->audit->record(
            action: AuditLog::EMPLOYEE_ADDRESS_CHANGED,
            actor: $request->user(),
            entityType: 'employee',
            entityId: $employee->user?->user_id ?? (string) $employee->id,
            after: ($existing === null ? 'Recorded: ' : 'Changed: ').implode(', ', $changed),
            request: $request,
        );
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
            // Not master data: the three types are rules in code — the staff ID
            // digit, who the clock applies to, which fields are required — and
            // a fourth one somebody typed into a list would satisfy none of them.
            'employmentTypes' => EmployeePresenter::employmentTypeOptions(),
        ];
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
