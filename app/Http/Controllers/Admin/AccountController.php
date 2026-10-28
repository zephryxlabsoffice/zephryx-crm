<?php

namespace App\Http\Controllers\Admin;

use App\Mail\AccountInviteMail;
use App\Mail\PasswordResetMail;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\MasterDataItem;
use App\Models\Role;
use App\Models\User;
use App\Support\Admin\AccessDirectory;
use App\Support\Admin\AccountRemoval;
use App\Support\Admin\AccountDirectory;
use App\Support\Audit\AuditLog;
use App\Support\Auth\PasswordResets;
use App\Support\Auth\RememberMe;
use App\Support\Auth\SecurityLock;
use App\Support\Auth\TrustedDevices;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use App\Support\StaffEmails;
use App\Support\StaffId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Login accounts and the roles they hold.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS IS NOT THE EMPLOYEE DIRECTORY, AND THE SPLIT IS DELIBERATE
 *
 * Employees owns the HR record: name, department, designation, reporting line,
 * date of birth, documents. Those belong to HR and are edited there.
 *
 * This page owns the ACCOUNT: whether it can sign in, and what it may do. Those
 * belong to the owner. The two are different questions asked by different
 * people, and merging them would mean either HR could grant permissions or the
 * owner could edit somebody's date of birth - both wrong.
 *
 * §8 gives `user_roles` an `assigned_by`, which is the column that only makes
 * sense if role assignment happens somewhere with an accountable actor. This is
 * that somewhere, and the column is written.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * HOW THE FOUR OBLIGATIONS ARE MET
 *
 * 1. NOTHING DELETES AN ACCOUNT. There is no delete route and no delete call.
 *    Suspending stops the sign-in; the person's attendance, leave and payslips
 *    stay exactly where they are and keep naming them.
 *
 * 2. THE OWNER ACCOUNT CANNOT BE SUSPENDED OR STRIPPED HERE. It is absent from
 *    AccountDirectory's query entirely, so the routes 404 on it rather than
 *    refusing it - a row nobody may act on is a row that invites the attempt.
 *
 * 3. SUSPENDING KILLS LIVE SESSIONS, REMEMBER-ME CHAINS AND TRUSTED DEVICES. An
 *    account that cannot sign in but is already signed in is not suspended
 *    (§4.4, §4.6), and a remembered cookie would sign it straight back in.
 *
 * 4. EVERY CHANGE IS AUDITED with before and after (§6) - and the roles entry
 *    names what MOVED rather than the resulting set, because "now holds
 *    employee, hr" does not say whether hr was just granted.
 */
class AccountController extends Controller
{
    public function __construct(
        protected AuditLog $audit,
        protected Rbac $rbac,
        protected RememberMe $remember,
        protected TrustedDevices $devices,
        protected PasswordResets $resets,
    ) {
    }

    public function index(Request $request): Response
    {
        return response()->view('admin.accounts.index', [
            'ar' => $this->route(''),
            'activeNav' => 'accounts',
            'accounts' => AccountDirectory::all(),
            'stats' => AccountDirectory::stats(),
        ]);
    }

    /**
     * GET /admin/accounts/new - add a staff account.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WHY THE ADMIN PANEL CAN ADD PEOPLE (decided 2026-10-27)
     *
     * Adding an employee is HR's act, in Employees. But a fresh install has
     * only the owner, and the owner's realm has no Employees module - so there
     * was nobody who could create the first HR account. This form is the way
     * in, and the owner asked for it to stay available, not only for the first
     * one.
     *
     * It asks for the minimum: who, how employed, since when, and which roles.
     * The person gets the same invite email HR's form sends; HR fills in the
     * department, documents and salary afterwards in Employees.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function create(Request $request): Response
    {
        return response()->view('admin.accounts.create', [
            'ar' => $this->route(''),
            'activeNav' => 'accounts',
            'kind' => old('kind', $request->query('kind')) === 'normal' ? 'normal' : 'employee',
            'roles' => AccessDirectory::roles()->keyBy('key'),
            'types' => [
                Employee::FULL_TIME => 'Full-time',
                Employee::INTERN => 'Intern',
                Employee::FREELANCE => 'Freelance',
            ],
        ]);
    }

    /**
     * POST /admin/accounts
     */
    public function store(Request $request): RedirectResponse
    {
        /*
         * Two kinds (2026-10-27). An EMPLOYEE is a person with attendance,
         * leave and payslips. A NORMAL account - hr@, admin@ - holds roles and
         * nothing else: no employment record, so no attendance or payslip.
         * Only this panel creates normal accounts; HR and CEO add employees.
         */
        $normal = $request->input('kind') === 'normal';

        $data = $request->validate([
            'kind' => ['nullable', Rule::in(['employee', 'normal'])],
            'name' => ['required', 'string', 'max:120'],
            // Employee: personal required, work optional - see StaffEmails.
            'personal_email' => [$normal ? 'nullable' : 'required', 'email', 'max:190'],
            'work_email' => ['nullable', 'email', 'max:190'],
            'phone' => [$normal ? 'nullable' : 'required', 'string', 'max:32'],
            'employment_type' => [$normal ? 'nullable' : 'required', Rule::in(Employee::TYPES)],
            'joined_on' => [$normal ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            // Normal account: one address, the one it signs in with.
            'account_email' => [$normal ? 'required' : 'nullable', 'email', 'max:190'],
            'roles' => [$normal ? 'required' : 'nullable', 'array'],
            'roles.*' => [Rule::exists('roles', 'role_key')],
        ], [
            'roles.required' => 'A normal account can do nothing without a role. Tick at least one.',
        ]);

        $this->guardTopRoles([], $data['roles'] ?? []);

        if ($normal) {
            return $this->storeNormal($request, $data);
        }

        $taken = collect(['personal_email', 'work_email'])
            ->filter(fn (string $f) => trim((string) ($data[$f] ?? '')) !== ''
                && StaffEmails::takenByAnother((string) $data[$f], null))
            ->mapWithKeys(fn (string $f) => [$f => 'This email is already used by another account.'])
            ->all();

        if ($taken !== []) {
            throw ValidationException::withMessages($taken);
        }

        // Everybody on staff is at least an Employee; the form cannot leave it off.
        $roleKeys = collect($data['roles'] ?? [])->push('employee')->unique()->values();

        $user = DB::transaction(function () use ($data, $roleKeys, $request) {
            $user = User::create([
                'user_id' => StaffId::forEmployee($data['employment_type']),
                'name' => $data['name'],
                'email' => StaffEmails::mailAddress($data['personal_email'], $data['work_email'] ?? null),
                // Never used - replaced when they follow the invite link.
                'password' => Str::random(64),
                'account_type' => Realm::STAFF,
                'staff_kind' => 'employee',
                'status' => 'active',
            ]);

            $user->roles()->sync(
                Role::whereIn('role_key', $roleKeys)->pluck('id')
                    ->mapWithKeys(fn (int $id) => [$id => [
                        'assigned_by' => $request->user()?->id,
                        'assigned_at' => now(),
                    ]])->all()
            );

            $employee = Employee::create([
                'user_id' => $user->id,
                'employment_type' => $data['employment_type'],
                'joined_on' => $data['joined_on'],
            ]);

            EmployeeProfile::create([
                'employee_id' => $employee->id,
                'phone' => trim($data['phone']),
                'personal_email' => trim($data['personal_email']),
            ]);

            return $user;
        });

        $token = $this->resets->issue($user, $request);

        Mail::to($user->email)->send(new AccountInviteMail(
            url: route('password.reset.form', ['token' => $token]),
            name: $user->name,
            staffId: $user->user_id,
            addedBy: $request->user()->name,
        ));

        $this->audit->record(
            action: AuditLog::ACCOUNT_CHANGED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $user->user_id,
            before: 'no account',
            after: 'created from the Admin Panel · '.$user->email.' · roles '.$roleKeys->join(', '),
            request: $request,
        );

        return redirect()
            ->route($this->route('show'), ['account' => $user->user_id])
            ->with('status', $user->name.' was added as '.$user->user_id.' and emailed a link to set their password. '
                .'HR can now complete their department, documents and salary in Employees.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/accounts/{account}/delete - only for an account made by
     * mistake and never used. See App\Support\Admin\AccountRemoval for the
     * rule and why it is this narrow.
     *
     * The email has to be typed back. A delete cannot be undone, and the
     * confirmation that stops a misclick is one that cannot be clicked.
     */
    public function destroy(Request $request, string $account): RedirectResponse
    {
        $user = $this->find($account);
        $removal = app(AccountRemoval::class);

        $blockers = $removal->blockers($user);

        if ($blockers !== []) {
            return redirect()->route($this->route('show'), ['account' => $user->user_id])
                ->with('status', 'This account cannot be deleted - it has been used: '.implode(', ', $blockers)
                    .'. Deactivate it instead.')
                ->with('status_tone', 'danger');
        }

        $request->validate([
            'confirm_email' => ['required', 'string', function ($attribute, $value, $fail) use ($user) {
                if (strcasecmp(trim((string) $value), (string) $user->email) !== 0) {
                    $fail('Type the account\'s email exactly to confirm.');
                }
            }],
        ]);

        $summary = $user->name.' · '.$user->user_id.' · '.$user->email;

        $removal->delete($user);

        $this->audit->record(
            action: AuditLog::ACCOUNT_DELETED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $user->user_id,
            before: $summary,
            after: 'deleted - made by mistake, never used',
            request: $request,
        );

        return redirect()->route($this->route('index'))
            ->with('status', $user->name.' ('.$user->user_id.') was deleted. The email '.$user->email.' can be used again.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/accounts/{account}/company - department, designation,
     * reporting line and joining date (2026-10-27).
     *
     * The same four facts HR edits in Employees, reachable from the panel too.
     * They describe who works where and decide no access at all - roles do.
     * Everything else on the record (documents, bank, salary) stays HR's.
     */
    public function company(Request $request, string $account): RedirectResponse
    {
        $user = $this->find($account);
        $employee = Employee::with(['department', 'designation', 'manager.user'])->where('user_id', $user->id)->first();

        abort_if($employee === null, 404);

        $data = $request->validate([
            'department_id' => ['nullable', Rule::exists('master_data_items', 'id')
                ->where('list', MasterDataItem::DEPARTMENTS)->where('is_active', true)],
            'designation_id' => ['nullable', Rule::exists('master_data_items', 'id')
                ->where('list', MasterDataItem::DESIGNATIONS)->where('is_active', true)],
            'reports_to' => ['nullable', 'integer', Rule::exists('employees', 'id'), Rule::notIn([$employee->id])],
            'joined_on' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'reports_to.not_in' => 'Somebody cannot report to themselves.',
        ]);

        $before = $this->companyLine($employee);

        $employee->update([
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'reports_to' => $data['reports_to'] ?? null,
            'joined_on' => $data['joined_on'],
        ]);

        $after = $this->companyLine($employee->fresh(['department', 'designation', 'manager.user']));

        if ($before === $after) {
            return redirect()->route($this->route('show'), ['account' => $user->user_id])
                ->with('status', 'Nothing changed.')
                ->with('status_tone', 'info');
        }

        $this->audit->record(
            action: AuditLog::EMPLOYEE_UPDATED,
            actor: $request->user(),
            entityType: 'employee',
            entityId: $user->user_id,
            before: $before,
            after: $after.' (from the Admin Panel)',
            request: $request,
        );

        return redirect()->route($this->route('show'), ['account' => $user->user_id])
            ->with('status', 'Company details saved.')
            ->with('status_tone', 'success');
    }

    protected function companyLine(Employee $employee): string
    {
        return implode(' · ', [
            $employee->department?->name ?? 'no department',
            $employee->designation?->name ?? 'no designation',
            'reports to '.($employee->manager?->user?->name ?? 'nobody'),
            'joined '.$employee->joined_on?->format('d M Y'),
        ]);
    }

    /**
     * A normal account: roles and a sign-in, and no employment.
     *
     * `staff_kind` stays null, which is what keeps the Employee base - own
     * attendance, leave, payslips - off it (User::hasEmployeeBase). The
     * Employee role is never added, for the same reason.
     *
     * @param  array<string, mixed>  $data
     */
    protected function storeNormal(Request $request, array $data): RedirectResponse
    {
        $email = trim((string) $data['account_email']);

        if (StaffEmails::takenByAnother($email, null)) {
            throw ValidationException::withMessages(['account_email' => 'This email is already used by another account.']);
        }

        $roleKeys = collect($data['roles'])->reject(fn ($k) => $k === 'employee')->unique()->values();

        if ($roleKeys->isEmpty()) {
            throw ValidationException::withMessages(['roles' => 'A normal account can do nothing without a role. Tick at least one.']);
        }

        $user = DB::transaction(function () use ($data, $email, $roleKeys, $request) {
            $user = User::create([
                'user_id' => StaffId::forNormalAccount(),
                'name' => $data['name'],
                'email' => $email,
                'password' => Str::random(64),
                'account_type' => Realm::STAFF,
                'staff_kind' => null,
                'status' => 'active',
            ]);

            $user->roles()->sync(
                Role::whereIn('role_key', $roleKeys)->pluck('id')
                    ->mapWithKeys(fn (int $id) => [$id => [
                        'assigned_by' => $request->user()?->id,
                        'assigned_at' => now(),
                    ]])->all()
            );

            return $user;
        });

        $token = $this->resets->issue($user, $request);

        Mail::to($user->email)->send(new AccountInviteMail(
            url: route('password.reset.form', ['token' => $token]),
            name: $user->name,
            staffId: $user->user_id,
            addedBy: $request->user()->name,
        ));

        $this->audit->record(
            action: AuditLog::ACCOUNT_CHANGED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $user->user_id,
            before: 'no account',
            after: 'normal account created from the Admin Panel · '.$user->email.' · roles '.$roleKeys->join(', '),
            request: $request,
        );

        return redirect()
            ->route($this->route('show'), ['account' => $user->user_id])
            ->with('status', $user->name.' was added as '.$user->user_id.' (normal account, not an employee) '
                .'and emailed a link to set the password.')
            ->with('status_tone', 'success');
    }

    public function show(Request $request, string $account): Response
    {
        $user = $this->find($account);

        $roles = AccessDirectory::roles()->keyBy('key');
        $held = AccessDirectory::rolesOf($user);

        /*
         * What this account can actually do - the UNION across its roles (§2.4)
         * plus whatever its type grants implicitly, which is what `permissionsFor`
         * answers. Read from the engine rather than assembled here: a second
         * implementation of the union is a second answer to "who can see
         * payroll", and one of them would be wrong.
         */
        $effective = collect($this->rbac->permissionsFor($user))->sort()->values();

        $employee = Employee::with(['department', 'designation', 'manager.user'])->where('user_id', $user->id)->first();

        return response()->view('admin.accounts.show', [
            'ar' => $this->route(''),
            'activeNav' => 'accounts',
            /*
             * Company details - who works where (2026-10-27). A record, never
             * access: roles decide what somebody may do. Null for a normal
             * account, which is nobody's employment.
             */
            'employee' => $employee,
            // Empty when the account may be deleted (made by mistake, unused).
            'deleteBlockers' => app(AccountRemoval::class)->blockers($user),
            'departments' => MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->active()->get(),
            'designations' => MasterDataItem::inList(MasterDataItem::DESIGNATIONS)->active()->get(),
            'managers' => $employee === null ? collect() : Employee::query()
                ->with('user')
                ->active()
                ->where('id', '!=', $employee->id)
                ->get()
                ->sortBy(fn (Employee $e) => $e->user?->name)
                ->values(),
            'account' => AccountDirectory::row($user),
            'roles' => $roles,
            'held' => $held,
            'effective' => $effective,
            'sensitive' => collect(AccessDirectory::sensitive())->filter(
                fn (string $key) => $effective->contains($key)
            )->values(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE WRITES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * POST /admin/accounts/{account}/status
     *
     * ─────────────────────────────────────────────────────────────────────────
     * SUSPENDING IS NOT A COLUMN CHANGE
     *
     * Setting `status` and stopping there produces an account that cannot sign
     * in and is still signed in - for up to a session's lifetime, and
     * indefinitely if it holds a remember-me cookie. The person suspending it
     * would be told it worked.
     *
     * So the four things go together, and they go in this order: the status
     * first, because it is the one that survives a crash halfway through, and
     * because Rbac::permissionsFor already refuses an inactive account
     * everything (see rule 2 there). Even if the session survived, it would
     * hold nothing.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function status(Request $request, string $account): RedirectResponse
    {
        $user = $this->find($account);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            // Required to suspend, not to restore. Taking somebody's access
            // away is the act that gets asked about later; giving it back is
            // its own explanation.
            'reason' => ['nullable', 'string', 'max:500', 'required_unless:status,active'],
        ]);

        $was = $user->status;

        if ($was === $data['status']) {
            return redirect()
                ->route($this->route('show'), ['account' => $user->user_id])
                ->with('status', 'Nothing changed.')
                ->with('status_tone', 'info');
        }

        $user->forceFill(['status' => $data['status']])->save();

        if ($data['status'] !== 'active') {
            $this->cutOff($user);
        }

        $this->rbac->forget($user);

        $this->audit->record(
            action: AuditLog::ACCOUNT_CHANGED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $user->user_id,
            before: $was,
            after: $data['status'].(($data['reason'] ?? null) ? ' - '.($data['reason'] ?? null) : '')
                .($data['status'] === 'active' ? '' : '; sessions, remember-me tokens and trusted devices revoked'),
            request: $request,
        );

        /*
         * Off a security hold: the old password may be known to whoever caused
         * it, so the person is sent a link to choose a new one. An admin's
         * link, so it carries no "This wasn't me" button (owner, 2026-10-28).
         */
        if ($was === SecurityLock::STATUS && $data['status'] === 'active') {
            $token = $this->resets->issue($user, $request);

            Mail::to($user->email)->send(new PasswordResetMail(
                url: route('password.reset.form', ['token' => $token]),
                name: $user->name,
                ip: $request->ip(),
            ));

            return redirect()
                ->route($this->route('show'), ['account' => $user->user_id])
                ->with('status', $user->name.' is off hold and has been emailed a link to choose a new password.')
                ->with('status_tone', 'success');
        }

        return redirect()
            ->route($this->route('show'), ['account' => $user->user_id])
            ->with('status', $data['status'] === 'active'
                ? $user->name.' can sign in again.'
                : $user->name.' can no longer sign in, and anywhere they were signed in has been signed out.')
            ->with('status_tone', $data['status'] === 'active' ? 'success' : 'info');
    }

    /**
     * POST /admin/accounts/{account}/roles
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE ENTRY SAYS WHAT MOVED, NOT WHAT THE SET NOW IS
     *
     * "Now holds employee, hr" does not say whether hr was granted just now or
     * has been there for a year - and the question somebody brings to an audit
     * log is always the first one. So the before and after are the sets, and
     * the after also names the difference.
     *
     * The Employee base is not in this list and cannot be: §5 grants it by
     * account type precisely so no role edit can revoke it. There is no
     * checkbox for it, and `Rule::exists` on `roles` means a request inventing
     * one is refused rather than silently ignored.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function roles(Request $request, string $account): RedirectResponse
    {
        $user = $this->find($account);

        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::exists('roles', 'role_key')],
        ]);

        /*
         * A client account holds no roles by construction (§2.2): everything it
         * can reach comes from Rbac::CLIENT_BASE. Granting it a staff role
         * would put keys on an account that realm middleware refuses anyway,
         * which is a grant that reads as real and does nothing.
         */
        abort_if($user->account_type !== Realm::STAFF, 403);

        $was = collect(AccessDirectory::rolesOf($user))->sort()->values();

        $this->guardTopRoles(
            $user->roles()->pluck('role_key'),
            $data['roles'] ?? [],
        );

        $ids = Role::whereIn('role_key', $data['roles'] ?? [])->pluck('id');

        $user->roles()->sync(
            $ids->mapWithKeys(fn (int $id) => [$id => [
                'assigned_by' => $request->user()?->id,
                'assigned_at' => now(),
            ]])->all()
        );

        $user->load('roles');
        $this->rbac->forget($user);

        $now = collect(AccessDirectory::rolesOf($user))->sort()->values();

        if ($was->all() === $now->all()) {
            return redirect()
                ->route($this->route('show'), ['account' => $user->user_id])
                ->with('status', 'Nothing changed.')
                ->with('status_tone', 'info');
        }

        $granted = $now->diff($was);
        $revoked = $was->diff($now);

        $this->audit->record(
            action: AuditLog::ACCOUNT_CHANGED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $user->user_id,
            before: $was->isEmpty() ? 'no roles' : $was->join(', '),
            after: implode(' · ', array_filter([
                $now->isEmpty() ? 'no roles' : $now->join(', '),
                $granted->isEmpty() ? null : 'granted '.$granted->join(', '),
                $revoked->isEmpty() ? null : 'revoked '.$revoked->join(', '),
            ])),
            request: $request,
        );

        return redirect()
            ->route($this->route('show'), ['account' => $user->user_id])
            ->with('status', 'Roles saved.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/accounts/{account}/force-password-reset
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THREE DISTINCT BUTTONS, ONE PROBLEM EACH (review round Q11)
     *
     * A forgotten or possibly-known password, a stolen session, and a
     * compromised device are three different things to have happened to an
     * account, and none of the three routes below stands in for another.
     * This one answers the first: it puts the account through exactly the
     * self-service reset flow (`PasswordResetController`) would, on the
     * admin's say-so rather than the owner's own click on an email link, and
     * - because a "forced" reset that left the old password and an
     * already-open session still working would not be forcing anything -
     * cuts off everywhere they are currently signed in at the same time.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function forcePasswordReset(Request $request, string $account): RedirectResponse
    {
        $user = $this->find($account);

        $token = $this->resets->issue($user, $request);

        Mail::to($user->email)->send(new PasswordResetMail(
            url: route('password.reset.form', ['token' => $token]),
            name: $user->name,
            ip: $request->ip(),
        ));

        $this->cutOff($user);

        $this->audit->record(
            action: AuditLog::ACCOUNT_PASSWORD_RESET_FORCED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $user->user_id,
            after: 'Reset link emailed; sessions, remember-me tokens and trusted devices revoked',
            request: $request,
        );

        return redirect()
            ->route($this->route('show'), ['account' => $user->user_id])
            ->with('status', $user->name.' has been emailed a reset link, and is signed out everywhere until they use it.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/accounts/{account}/sign-out
     *
     * The second of the three (see forcePasswordReset above): a session or a
     * remember-me chain believed to be in the wrong hands, with nothing wrong
     * with the password itself. Device trust is untouched on purpose - the
     * next sign-in still needs the password, which is the actual barrier a
     * stolen browser tab does not get past.
     */
    public function signOutEverywhere(Request $request, string $account): RedirectResponse
    {
        $user = $this->find($account);

        $this->remember->revokeAll($user);
        $this->killSessions($user);

        $this->audit->record(
            action: AuditLog::ACCOUNT_SESSIONS_REVOKED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $user->user_id,
            after: 'Sessions and remember-me tokens revoked; password and device trust untouched',
            request: $request,
        );

        return redirect()
            ->route($this->route('show'), ['account' => $user->user_id])
            ->with('status', $user->name.' has been signed out everywhere.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/accounts/{account}/untrust-devices
     *
     * The third (see forcePasswordReset above): a device that should have to
     * prove itself with a code again, without touching whatever session or
     * password is already working. The one of the three that does not sign
     * anybody out - a currently open session is not device trust, it is a
     * session, and stays open until it expires or one of the other two acts.
     */
    public function untrustDevices(Request $request, string $account): RedirectResponse
    {
        $user = $this->find($account);

        $this->devices->revokeAll($user);

        $this->audit->record(
            action: AuditLog::ACCOUNT_DEVICES_UNTRUSTED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $user->user_id,
            after: 'Every trusted device revoked; the next sign-in from any of them asks for a code again',
            request: $request,
        );

        return redirect()
            ->route($this->route('show'), ['account' => $user->user_id])
            ->with('status', 'Every device trusted for '.$user->name.' has been forgotten. The next sign-in anywhere asks for a code.')
            ->with('status_tone', 'success');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * The account, or a 404.
     *
     * Looked up through AccountDirectory, which excludes the owner - so these
     * routes cannot reach it at all. See the head of that class.
     */
    protected function find(string $userId): User
    {
        $user = AccountDirectory::find($userId);

        abort_if($user === null, 404);

        /*
         * A System Administrator reaches these pages from the staff side
         * (owner, 2026-10-28) and is a person with an account of their own, so
         * the rules for acting on people apply (§2.6): not on themselves, and
         * not on anybody who does not rank below them in `system` - which
         * leaves a second System Administrator to the owner.
         */
        $actor = request()->user();

        if ($actor !== null && $actor->account_type === Realm::STAFF) {
            abort_if($actor->is($user) || ! $this->rbac->outranks($actor, $user, 'system'), 403);
        }

        return $user;
    }

    /**
     * The route name for an accounts page, on whichever side this request came
     * from: the Admin Panel (`admin.accounts.*`) or a System Administrator's
     * staff sidebar (`staff-accounts.*`).
     */
    protected function route(string $name): string
    {
        return (request()->is('admin/*') ? 'admin.accounts.' : 'staff-accounts.').$name;
    }

    /**
     * Roles a System Administrator may neither give nor take away: their own,
     * and the CEO's. Only the owner hands out the top of the ladder.
     *
     * @param  iterable<string>  $before
     * @param  iterable<string>  $after
     */
    protected function guardTopRoles(iterable $before, iterable $after): void
    {
        if (request()->user()?->account_type !== Realm::STAFF) {
            return;
        }

        $changed = collect($before)->diff($after)->merge(collect($after)->diff($before));

        if ($changed->intersect(['system_admin', 'ceo'])->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'Only the owner can give or take away the CEO and System Administrator roles.',
            ]);
        }
    }

    /**
     * Everything a suspension has to take away besides the column.
     */
    protected function cutOff(User $user): void
    {
        $this->remember->revokeAll($user);
        $this->devices->revokeAll($user);
        $this->killSessions($user);
    }

    /**
     * Delete every live session row for a user - the one part of "signed out
     * everywhere" that needs the database session driver to reach at all.
     *
     * Shared by `cutOff` (suspend, force-password-reset) and
     * `signOutEverywhere`, so the misconfiguration warning below is written
     * once rather than copied at each call site.
     */
    protected function killSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            /*
             * On `file` or `cookie` there is no way to reach another session,
             * so an account this was meant to cut off stays signed in until
             * its session expires - while the panel reports that it has been
             * handled.
             */
            Log::error('An account action could not invalidate other sessions.', [
                'driver' => config('session.driver'),
                'user_id' => $user->user_id,
                'why' => 'SESSION_DRIVER must be `database` for §4.4/§4.6 - other sessions cannot be '
                    .'reached on this driver and stay signed in.',
            ]);

            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
