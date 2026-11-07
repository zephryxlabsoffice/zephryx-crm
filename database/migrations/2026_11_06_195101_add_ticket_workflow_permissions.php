<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The ticket process (owner, 2026-11-06). Added here as well as in RbacSeeder and
 * MasterDataSeeder because the seeders do not run on an update to a live site.
 *
 * - `tickets.view.all`: every ticket. Support, the CEO, System Administrators, and
 *   Mentors (who already read everything). Everybody else now sees their own, and a
 *   Manager their projects', clients' and people's.
 * - `tickets.take`: taking any ticket oneself. The CEO and System Administrators.
 * - The "Change request" ticket category, for clients asking for project changes.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, roles: list<string>}> */
    protected array $grants = [
        'tickets.view.all' => [
            'name' => 'See every ticket - tickets.view.all',
            'roles' => ['support', 'ceo', 'system_admin', 'mentor'],
        ],
        'tickets.take' => [
            'name' => 'Take any ticket oneself - tickets.take',
            'roles' => ['ceo', 'system_admin'],
        ],
    ];

    public function up(): void
    {
        if (DB::table('permissions')->exists()) {
            foreach ($this->grants as $key => $grant) {
                DB::table('permissions')->insertOrIgnore([
                    'permission_key' => $key,
                    'permission_name' => $grant['name'],
                    'module' => 'tickets',
                    'is_sensitive' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $permissionId = DB::table('permissions')->where('permission_key', $key)->value('id');

                foreach (DB::table('roles')->whereIn('role_key', $grant['roles'])->pluck('id') as $roleId) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        }

        if (DB::table('master_data_items')->where('list', 'ticket-categories')->exists()) {
            DB::table('master_data_items')->insertOrIgnore([
                'list' => 'ticket-categories',
                'code' => 'CHANGE',
                'name' => 'Change request',
                'is_active' => true,
                'sort_order' => (int) DB::table('master_data_items')->where('list', 'ticket-categories')->max('sort_order') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('permission_key', array_keys($this->grants))->delete();
        DB::table('master_data_items')->where('list', 'ticket-categories')->where('code', 'CHANGE')->delete();
    }
};
