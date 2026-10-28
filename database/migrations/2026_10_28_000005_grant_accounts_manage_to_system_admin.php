<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The Accounts pages for the System Administrator role (owner, 2026-10-28).
 * The permission row is new, so it is made here as well as in RbacSeeder: the
 * live site is only ever migrated after its first install.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'permission_key' => 'accounts.manage',
            'permission_name' => 'Manage - accounts.manage',
            'module' => 'accounts',
            'is_sensitive' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('permission_key', 'accounts.manage')->value('id');
        $roleId = DB::table('roles')->where('role_key', 'system_admin')->value('id');

        if ($permissionId !== null && $roleId !== null) {
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
