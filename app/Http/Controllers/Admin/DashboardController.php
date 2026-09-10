<?php

namespace App\Http\Controllers\Admin;

use App\Support\Admin\AccessDirectory;
use App\Support\Admin\AccountDirectory;
use App\Support\Admin\MasterDataDirectory;
use App\Support\Admin\SettingsCatalogue;
use App\Support\Demo\DemoAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * The Admin Panel's overview.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THIS PAGE IS NOT
 *
 * It is not a company dashboard. No headcount trend, no revenue, no project
 * status — §2.1 gives this account no operational authority and no personal
 * records, and the owner holds a staff account for exactly that view. A second
 * copy of it here would be a page maintained twice and read once.
 *
 * What this account actually needs to know is whether the SYSTEM is set up
 * correctly and what changed lately: who holds the dangerous permissions,
 * which accounts are in a state nobody intended, which lists are empty, and
 * what has been done in the panel recently.
 *
 * Everything below is a loose end or a recent action. If the page is boring,
 * the configuration is in good order, which is the correct resting state for
 * an administration screen.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $accounts = AccountDirectory::stats();

        return response()->view('admin.dashboard', [
            'activeNav' => 'dashboard',

            'accounts' => [
                'total' => $accounts['total'],
                'active' => $accounts['active'],
                'suspended' => $accounts['inactive'],
            ],

            /*
             * Who can do the dangerous things, whatever role they got it
             * through. The union is the thing nobody can compute by reading a
             * list of roles, and "who can see payroll" is the question this
             * page exists to answer without being asked.
             */
            'sensitive' => collect(AccessDirectory::sensitive())->map(fn (string $key) => [
                'key' => $key,
                'holders' => AccessDirectory::whoHolds($key),
            ]),

            'attention' => $this->attention(),

            'recent' => DemoAudit::all()->take(6),
            'notable' => DemoAudit::notable(),
        ]);
    }

    /**
     * The loose ends. Each one is a thing somebody meant to finish.
     *
     * @return list<array<string, mixed>>
     */
    protected function attention(): array
    {
        $items = [];

        /*
         * An account that cannot sign in but still holds roles. Harmless while
         * it is inactive and a live account the moment somebody reactivates it
         * without checking what it can do.
         *
         * Counted with a query rather than in PHP now that these are rows: the
         * demo version pulled every employee into memory to filter two of them,
         * which was fine at twelve people and is not the shape to leave behind.
         */
        $strandedRoles = AccountDirectory::query()
            ->whereNot('status', 'active')
            ->whereHas('roles')
            ->count();

        if ($strandedRoles > 0) {
            $items[] = [
                'label' => 'Suspended accounts that still hold roles',
                'count' => $strandedRoles,
                'note' => 'Harmless until somebody reactivates one without checking what it can do.',
                'route' => 'admin.accounts.index',
                'tone' => 'tone-warn',
            ];
        }

        /*
         * An account with no roles cannot do anything, including the things
         * whoever created it assumed it could.
         *
         * Staff only. A client account holds no roles by construction (§2.2),
         * so counting them would report a loose end for every client the
         * company has — and the page would be permanently, uselessly amber.
         */
        $noRoles = AccountDirectory::stats()['no_roles'];

        if ($noRoles > 0) {
            $items[] = [
                'label' => 'Accounts with no roles',
                'count' => $noRoles,
                'note' => 'They can sign in and do nothing.',
                'route' => 'admin.accounts.index',
                'tone' => 'tone-warn',
            ];
        }

        // A lookup list with nothing in it is a module nobody can use yet.
        foreach (MasterDataDirectory::overview() as $meta) {
            if ($meta['empty']) {
                $items[] = [
                    'label' => $meta['label'].' is empty',
                    'count' => 0,
                    'note' => 'Records that need one of these cannot be created.',
                    'route' => 'admin.master.index',
                    'tone' => 'tone-warn',
                ];
            }
        }

        /*
         * Not a fault — a standing caution. These settings re-judge records
         * that already exist, and the person who edits them once a year should
         * be reminded of that before they open the page, not after.
         */
        $items[] = [
            'label' => 'Settings that rewrite past records',
            'count' => count(SettingsCatalogue::retroactiveKeys()),
            'note' => 'Attendance and leave are derived on read, so changing these re-judges history.',
            'route' => 'admin.settings',
            'tone' => 'tone-accent',
        ];

        return $items;
    }
}
