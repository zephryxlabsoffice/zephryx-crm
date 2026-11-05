<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A project manager may mark a missing End of Day urgent for people on their projects.
 *
 * Given to the Employee role alone, because anybody can be made a project manager and
 * every employee holds that role. So one tick in Access Controls turns it off. It
 * reaches nobody beyond the projects they manage. It is added here as well as in
 * RbacSeeder because the seeder does not run on an update to a live site.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('permissions')->exists()) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'permission_key' => 'tasks.eod.urgent',
            'permission_name' => 'Project managers mark reports urgent - tasks.eod.urgent',
            'module' => 'tasks',
            'is_sensitive' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->where('role_key', 'employee')->value('id');
        $permissionId = DB::table('permissions')->where('permission_key', 'tasks.eod.urgent')->value('id');

        if ($roleId !== null && $permissionId !== null) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('permission_key', 'tasks.eod.urgent')->delete();
    }
};
