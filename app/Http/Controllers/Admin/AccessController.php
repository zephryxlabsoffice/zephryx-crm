<?php

namespace App\Http\Controllers\Admin;

use App\Models\Domain;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AccessDirectory;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Access Control — which roles hold which permissions, and where each role
 * ranks in each domain.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE PROBLEM THIS SCREEN HAS TO SOLVE: ACTION AT A DISTANCE
 *
 * Ticking a permission on a role is the most consequential click in the
 * application, and it is made while looking at a role NAME. The consequence
 * lands on people whose names are nowhere on the screen. "Manager" is an
 * abstraction; Rahul Mehta and Vikram Joshi are who actually gains the ability
 * to read everybody's pay.
 *
 * So every toggle names its blast radius: how many people gain or lose the
 * permission, and who. AccessDirectory::whoWouldHold computes it, and it subtracts
 * people who already hold the permission through another role — because roles
 * stack as a union (§2.4), granting salary.view to Manager changes nothing for
 * a Manager who is also HR, and counting them would overstate the change.
 *
 * A permission-matrix screen without that sentence is how an organisation ends
 * up not knowing who can see payroll.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * WHAT IS NOT EDITABLE HERE
 *
 * The Employee base. §5 is explicit: it is not a role, and it is granted
 * implicitly to every staff account of kind `employee` so that it can never be
 * accidentally revoked by a role edit. There is no control for it on this page
 * and there must not be one — an owner who removed it would take everyone's own
 * attendance, leave and payslips away from them at once.
 *
 * Rank never grants anything either (§2.5). It answers "may I act on this
 * person" and routes approvals, and it is shown in its own section for that
 * reason, well away from the permission list.
 */
class AccessController extends Controller
{
    public function __construct(protected AuditLog $audit, protected Rbac $rbac) {}

    public function index(Request $request): Response
    {
        return response()->view('admin.access.index', [
            'activeNav' => 'access',
            'roles' => AccessDirectory::roles(),
            'domains' => AccessDirectory::domains(),
            'permissions' => AccessDirectory::permissions(),
            /*
             * Who can do the sensitive things right now, whatever role they got
             * it through. The question an owner opens this page to answer is
             * usually "who can see payroll", and it should not require reading
             * nine roles and doing the union in their head.
             */
            'sensitive' => collect(AccessDirectory::sensitive())->map(fn (string $key) => [
                'key' => $key,
                'holders' => AccessDirectory::whoHolds($key),
            ]),
        ]);
    }

    /**
     * GET /admin/access/create
     *
     * ─────────────────────────────────────────────────────────────────────────
     * "AN ADMIN MAY CREATE A ROLE" (review round Q12, answered 2026-09-21)
     *
     * Everything below is the same form `show`/`update` already render and
     * write — permissions grouped by module, rank per domain — with a name
     * and a key added at the top. A new role is not a different KIND of
     * screen from editing one; it is the same screen with nothing ticked yet.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function create(Request $request): Response
    {
        return response()->view('admin.access.create', [
            'activeNav' => 'access',
            'permissions' => AccessDirectory::permissions(),
            'domains' => AccessDirectory::domains(),
        ]);
    }

    /**
     * POST /admin/access
     *
     * A blank role — no permissions, rank 0 everywhere — is a valid save.
     * Refusing an empty grant would push somebody to tick something just to
     * get past validation, which is a worse outcome than a role that starts
     * doing nothing and is edited on the next visit to `show`.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_key' => [
                'required', 'string', 'max:32', 'regex:/^[a-z_]+$/',
                Rule::notIn(['create']),
                Rule::unique('roles', 'role_key'),
            ],
            'role_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(AccessDirectory::assignableKeys())],
            'ranks' => ['nullable', 'array'],
            'ranks.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [
            'role_key.regex' => 'Lowercase letters and underscores only, e.g. support_lead.',
        ]);

        $role = Role::create([
            'role_key' => $data['role_key'],
            'role_name' => $data['role_name'],
            'description' => $data['description'] ?? '',
            'is_active' => true,
        ]);

        $role->permissions()->sync(
            Permission::whereIn('permission_key', $data['permissions'] ?? [])->pluck('id')
        );

        $domainIds = Domain::pluck('id', 'domain_key');

        foreach ($data['ranks'] ?? [] as $domainKey => $rank) {
            if (! isset($domainIds[$domainKey])) {
                // A stale or invented key in the payload — nothing in
                // AccessDirectory::domains() offers one that would not
                // exist, so this is a request built by hand, not a form.
                continue;
            }

            DB::table('role_domain_rank')->insert([
                'role_id' => $role->id,
                'domain_id' => $domainIds[$domainKey],
                'rank' => (int) $rank,
            ]);
        }

        $this->rbac->forget();

        $this->audit->record(
            action: AuditLog::ROLE_CREATED,
            actor: $request->user(),
            entityType: 'role',
            entityId: $role->role_key,
            after: $role->role_name.' — '.($role->permissions()->count()).' permissions',
            request: $request,
        );

        return redirect()
            ->route('admin.access.show', ['role' => $role->role_key])
            ->with('status', $role->role_name.' created. Nobody holds it yet — assign it from an account.')
            ->with('status_tone', 'success');
    }

    public function show(Request $request, string $role): Response
    {
        $record = AccessDirectory::role($role);

        abort_if($record === null, 404);

        $permissions = AccessDirectory::permissions();

        /*
         * The blast radius of every toggle on the page, computed up front so
         * the view has no logic in it and the numbers cannot be assembled
         * differently in two places.
         */
        $impact = [];

        foreach ($permissions as $module => $entries) {
            foreach ($entries as $entry) {
                $impact[$entry['key']] = AccessDirectory::whoWouldHold($entry['key'], $role);
            }
        }

        return response()->view('admin.access.show', [
            'activeNav' => 'access',
            'role' => $record,
            'permissions' => $permissions,
            'impact' => $impact,
            'domains' => AccessDirectory::domains(),
            'sensitive' => AccessDirectory::sensitive(),
        ]);
    }

    /**
     * POST /admin/access/{role}
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE AUDIT ENTRY NAMES THE PEOPLE, NOT THE KEYS
     *
     * §6 wants a before and an after, and here the honest after is not "now
     * holds 14 permissions". Ticking a box on a role is action at a distance:
     * the entry that answers the question somebody brings six months later is
     * "granted salary.view, which gave it to Rahul Mehta and Vikram Joshi".
     *
     * Computed BEFORE the write, because afterwards the answer to "who would
     * this land on" is "nobody, they hold it already".
     *
     * WHAT THIS ROUTE CANNOT DO
     *
     * Grant `admin.*` or `client.*`. Both are realm bases (§2.1, §2.2) — held
     * by account type, never by a role — and the validator refuses them rather
     * than silently dropping them, because a toggle that appears to work and
     * grants nothing is worse than one that says no.
     *
     * Touch the Employee base either: it is not a permission on a role at all,
     * so there is nothing here that could reach it.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function update(Request $request, string $role): RedirectResponse
    {
        $record = Role::where('role_key', $role)->first();

        abort_if($record === null, 404);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(AccessDirectory::assignableKeys())],
        ]);

        $was = $record->permissions->pluck('permission_key')->sort()->values();
        $now = collect($data['permissions'] ?? [])->unique()->sort()->values();

        $granted = $now->diff($was)->values();
        $revoked = $was->diff($now)->values();

        if ($granted->isEmpty() && $revoked->isEmpty()) {
            return redirect()
                ->route('admin.access.show', ['role' => $role])
                ->with('status', 'Nothing changed.')
                ->with('status_tone', 'info');
        }

        // Who each change lands on, while it is still true.
        $landsOn = $granted->merge($revoked)
            ->mapWithKeys(fn (string $key) => [
                $key => AccessDirectory::whoWouldHold($key, $role)->pluck('name')->all(),
            ]);

        $record->permissions()->sync(Permission::whereIn('permission_key', $now)->pluck('id'));

        // Roles changed inside this request, so anything already resolved is
        // stale — including the session of the person who just saved.
        $this->rbac->forget();

        $this->audit->record(
            action: AuditLog::PERMISSION_CHANGED,
            actor: $request->user(),
            entityType: 'role',
            entityId: $record->role_key,
            before: $was->isEmpty() ? 'no permissions' : $was->join(', '),
            after: $this->describe($granted, $revoked, $landsOn),
            request: $request,
        );

        return redirect()
            ->route('admin.access.show', ['role' => $role])
            ->with('status', $this->summarise($granted, $revoked, $landsOn))
            ->with('status_tone', 'success');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * The audit entry's `after`: what moved, and onto whom.
     *
     * @param  Collection<int, string>  $granted
     * @param  Collection<int, string>  $revoked
     * @param  Collection<string, list<string>>  $landsOn
     */
    protected function describe(Collection $granted, Collection $revoked, Collection $landsOn): string
    {
        $lines = [];

        foreach (['granted' => $granted, 'revoked' => $revoked] as $verb => $keys) {
            foreach ($keys as $key) {
                $people = $landsOn->get($key, []);

                $lines[] = $verb.' '.$key.' ('.($people === []
                    ? 'nobody holds this role'
                    : count($people).': '.implode(', ', $people)).')';
            }
        }

        return implode(' · ', $lines);
    }

    /**
     * The sentence on the page afterwards.
     *
     * Counts rather than the full list: the flash message is read in passing,
     * and the names are one click away in the audit log where somebody is
     * looking for them.
     *
     * @param  Collection<int, string>  $granted
     * @param  Collection<int, string>  $revoked
     * @param  Collection<string, list<string>>  $landsOn
     */
    protected function summarise(Collection $granted, Collection $revoked, Collection $landsOn): string
    {
        $people = $landsOn->flatten()->unique()->count();

        $parts = array_filter([
            $granted->isEmpty() ? null : $granted->count().' granted',
            $revoked->isEmpty() ? null : $revoked->count().' revoked',
        ]);

        return implode(', ', $parts).'. '.($people === 0
            ? 'Nobody holds this role, so nobody is affected today.'
            : ($people === 1 ? 'One person is affected.' : $people.' people are affected.'));
    }
}
