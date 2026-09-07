<?php

namespace App\Http\Controllers\Admin;

use App\Support\Demo\DemoRbac;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

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
 * permission, and who. DemoRbac::whoWouldHold computes it, and it subtracts
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
    public function index(Request $request): Response
    {
        $roles = DemoRbac::roles();

        return response()->view('admin.access.index', [
            'activeNav' => 'access',
            'roles' => $roles,
            'domains' => DemoRbac::domains(),
            'permissions' => DemoRbac::permissions(),
            /*
             * Who can do the sensitive things right now, whatever role they got
             * it through. The question an owner opens this page to answer is
             * usually "who can see payroll", and it should not require reading
             * nine roles and doing the union in their head.
             */
            'sensitive' => collect(DemoRbac::sensitive())->map(fn (string $key) => [
                'key' => $key,
                'holders' => DemoRbac::whoHolds($key),
            ]),
        ]);
    }

    public function show(Request $request, string $role): Response
    {
        $record = DemoRbac::role($role);

        abort_if($record === null, 404);

        $permissions = DemoRbac::permissions();

        /*
         * The blast radius of every toggle on the page, computed up front so
         * the view has no logic in it and the numbers cannot be assembled
         * differently in two places.
         */
        $impact = [];

        foreach ($permissions as $module => $entries) {
            foreach ($entries as $entry) {
                $impact[$entry['key']] = DemoRbac::whoWouldHold($entry['key'], $role);
            }
        }

        return response()->view('admin.access.show', [
            'activeNav' => 'access',
            'role' => $record,
            'permissions' => $permissions,
            'impact' => $impact,
            'domains' => DemoRbac::domains(),
            'sensitive' => DemoRbac::sensitive(),
        ]);
    }
}
