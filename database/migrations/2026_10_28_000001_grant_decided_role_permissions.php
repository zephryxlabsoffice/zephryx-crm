<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Role permissions brought in line with the owner's decisions, so the live
 * site gets them through Admin → Updates rather than by hand.
 *
 * Added:
 * - System Administrator: `meetings.schedule` — "only a project manager or
 *   the system admin creates" a meeting (spec, Meetings, 2026-08-28).
 * - Manager: `tickets.triage` — a new client ticket goes to "the Support role
 *   and the Project Manager first; once they assign it…" (plan, Notifications).
 * - HR: `invoices.view` and `invoices.manage` — "CEO and HR" manage invoices
 *   (owner, 2026-10-28).
 *
 * Taken away:
 * - `leave.approve` from every role but HR and CEO — "only HR and CEO, no one
 *   else" (owner, 2026-10-28). This is the one deliberate revocation; RbacSeeder
 *   itself never removes a grant, so it has to happen here.
 */
return new class extends Migration
{
    protected const GRANTS = [
        'system_admin' => ['meetings.schedule'],
        'manager' => ['tickets.triage'],
        'hr' => ['invoices.view', 'invoices.manage'],
    ];

    protected const ONLY = [
        'leave.approve' => ['hr', 'ceo'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $roleKey => $permissionKeys) {
            $roleId = DB::table('roles')->where('role_key', $roleKey)->value('id');

            if ($roleId === null) {
                continue;
            }

            foreach ($permissionKeys as $permissionKey) {
                $permissionId = DB::table('permissions')->where('permission_key', $permissionKey)->value('id');

                if ($permissionId === null) {
                    continue;
                }

                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        foreach (self::ONLY as $permissionKey => $roleKeys) {
            $permissionId = DB::table('permissions')->where('permission_key', $permissionKey)->value('id');

            if ($permissionId === null) {
                continue;
            }

            DB::table('role_permissions')
                ->where('permission_id', $permissionId)
                ->whereNotIn('role_id', DB::table('roles')->whereIn('role_key', $roleKeys)->select('id'))
                ->delete();
        }
    }

    public function down(): void
    {
        // Deliberately nothing: a grant may have been given by hand before this
        // ran, and the decisions above are not ones a rollback should undo.
    }
};
