<?php

namespace App\Http\Controllers\Admin;

use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoRbac;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

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
 * owner could edit somebody's date of birth — both wrong.
 *
 * §8 gives `user_roles` an `assigned_by`, which is the column that only makes
 * sense if role assignment happens somewhere with an accountable actor. This is
 * that somewhere.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * WHAT THE BACKEND OWES
 *
 * 1. NOTHING DELETES AN ACCOUNT. Suspending stops the sign-in; the person's
 *    attendance, leave and payslips stay exactly where they are and keep naming
 *    them. A deleted user is a payroll record with nobody attached to it.
 *
 * 2. THE OWNER ACCOUNT CANNOT BE SUSPENDED OR STRIPPED HERE. It is the only
 *    account that can reach this page, so one click would lock the company out
 *    of its own configuration with no way back that does not involve the
 *    database.
 *
 * 3. SUSPENDING KILLS LIVE SESSIONS AND TRUSTED DEVICES. An account that cannot
 *    sign in but is already signed in is not suspended (§4.4, §4.6).
 *
 * 4. EVERY CHANGE IS AUDITED with before and after (§6).
 */
class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = DemoRbac::roles()->keyBy('key');

        $accounts = DemoEmployees::all()->map(fn (array $employee) => $employee + [
            'roles' => collect(DemoRbac::rolesOf($employee['user_id']))
                ->map(fn (string $key) => $roles->get($key))
                ->filter()
                ->values(),
        ]);

        return response()->view('admin.accounts.index', [
            'activeNav' => 'accounts',
            'accounts' => $accounts,
            'stats' => [
                'total' => $accounts->count(),
                'active' => $accounts->where('status', 'active')->count(),
                // The rows worth looking at: an account that cannot sign in but
                // still holds roles is a loose end, not a resting state.
                'inactive' => $accounts->where('status', 'inactive')->count(),
                'no_roles' => $accounts->filter(fn (array $a) => $a['roles']->isEmpty())->count(),
            ],
        ]);
    }

    public function show(Request $request, string $account): Response
    {
        $employee = DemoEmployees::all()->firstWhere('user_id', $account);

        abort_if($employee === null, 404);

        $roles = DemoRbac::roles()->keyBy('key');
        $held = DemoRbac::rolesOf($account);

        /*
         * What this person can actually do — the UNION across their roles
         * (§2.4), which is the thing nobody can work out by reading a list of
         * role names. Somebody who is Employee + HR holds more than either row
         * suggests, and this is the only place that says so plainly.
         */
        $effective = collect($held)
            ->flatMap(fn (string $key) => $roles->get($key)['permissions'] ?? [])
            ->unique()
            ->sort()
            ->values();

        return response()->view('admin.accounts.show', [
            'activeNav' => 'accounts',
            'account' => $employee,
            'roles' => $roles,
            'held' => $held,
            'effective' => $effective,
            'sensitive' => collect(DemoRbac::sensitive())->filter(
                fn (string $key) => $effective->contains($key)
            )->values(),
        ]);
    }
}
