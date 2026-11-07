<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ticket process, settled (owner, 2026-11-07).
 *
 * - A ticket can be given to a team (`team_id`), and records who resolved it
 *   (`resolved_by`).
 * - `tickets.it`: the System Administrator handles IT tickets only (Access,
 *   Network, Bug), so they lose `tickets.view.all` and gain this.
 * - `tickets.take` is gone: whoever may resolve a ticket resolves it directly.
 *
 * Safe whether or not 2026_11_06_195101 has run before it on this database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('assignee_id')
                    ->constrained('teams')->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->after('resolved_at')
                    ->constrained('employees')->nullOnDelete();
            }
        });

        // A database with no permissions yet is about to be seeded, which adds these.
        if (! DB::table('permissions')->exists()) {
            return;
        }

        $systemAdmin = DB::table('roles')->where('role_key', 'system_admin')->value('id');

        DB::table('permissions')->insertOrIgnore([
            'permission_key' => 'tickets.it',
            'permission_name' => 'Handle IT tickets - tickets.it',
            'module' => 'tickets',
            'is_sensitive' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($systemAdmin !== null) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $systemAdmin,
                'permission_id' => DB::table('permissions')->where('permission_key', 'tickets.it')->value('id'),
            ]);

            $viewAll = DB::table('permissions')->where('permission_key', 'tickets.view.all')->value('id');

            if ($viewAll !== null) {
                DB::table('role_permissions')->where('role_id', $systemAdmin)->where('permission_id', $viewAll)->delete();
            }
        }

        // Its grants and any designation exception go with it (cascade).
        $take = DB::table('permissions')->where('permission_key', 'tickets.take')->value('id');

        if ($take !== null) {
            DB::table('role_permissions')->where('permission_id', $take)->delete();
            DB::table('permissions')->where('id', $take)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('resolved_by');
        });

        DB::table('permissions')->where('permission_key', 'tickets.it')->delete();
    }
};
