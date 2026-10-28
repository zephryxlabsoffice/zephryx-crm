<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Grants that were decided but never given to the role, so the live site
 * gets them through Admin → Updates rather than by hand:
 *
 * - System Administrator: `meetings.schedule` — "only a project manager or
 *   the system admin creates" a meeting (spec, Meetings, 2026-08-28).
 * - Manager: `tickets.triage` — a new client ticket goes to "the Support role
 *   and the Project Manager first; once they assign it…" (plan, Notifications).
 *
 * Adds only. A grant somebody already gave by hand is left as it is, and
 * nothing is ever taken away — the same rule RbacSeeder follows.
 */
return new class extends Migration
{
    protected const GRANTS = [
        'system_admin' => ['meetings.schedule'],
        'manager' => ['tickets.triage'],
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
    }

    public function down(): void
    {
        // Deliberately nothing: a grant may have been given by hand before this
        // ran, and rolling back must not take it away.
    }
};
