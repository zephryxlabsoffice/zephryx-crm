<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * HR gets the Clients module in full (owner, 2026-10-28): see, add, edit,
 * change status and give portal access. Adds only - see 2026_10_28_000001.
 */
return new class extends Migration
{
    protected const KEYS = ['clients.view', 'clients.create', 'clients.edit', 'clients.status', 'clients.invite'];

    public function up(): void
    {
        $roleId = DB::table('roles')->where('role_key', 'hr')->value('id');

        if ($roleId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('permission_key', self::KEYS)->pluck('id') as $permissionId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Deliberately nothing - see 2026_10_28_000001.
    }
};
