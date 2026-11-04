<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A task can be given to somebody for later, and the CEO can read every report.
 *
 * `starts_on` null means it can be worked on now, which is what every existing task
 * has always meant. The permission is added here as well as in RbacSeeder because
 * the seeder does not run on an update to a live site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('priority');
        });

        if (! DB::table('permissions')->exists()) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'permission_key' => 'tasks.eod.all',
            'permission_name' => "See everybody's - tasks.eod.all",
            'module' => 'tasks',
            'is_sensitive' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->where('role_key', 'ceo')->value('id');
        $permissionId = DB::table('permissions')->where('permission_key', 'tasks.eod.all')->value('id');

        if ($roleId !== null && $permissionId !== null) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('starts_on');
        });
    }
};
