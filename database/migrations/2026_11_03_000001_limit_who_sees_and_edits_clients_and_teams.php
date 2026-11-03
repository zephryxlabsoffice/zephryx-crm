<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Who sees and edits clients and teams (owner, 2026-11-03).
 *
 * Adding and editing a client now belongs to Client Relations, the CEO and the
 * System Administrator, and nobody else. HR and Manager lose it.
 *
 * Two new keys decide who sees everything. A Manager without `clients.view.all`
 * sees only their own clients, and a Team Lead without `teams.view.all` only their
 * own teams, so every other role holding the plain key is given the wide one and
 * sees what it saw before. Employee is left out alongside Team Lead, because every
 * Team Lead also holds Employee, so plain employees see only their own teams too.
 *
 * RbacSeeder never removes a grant, so the taking away has to happen here. The
 * seeder has the same result for a fresh install, which has no permissions yet.
 */
return new class extends Migration
{
    protected const CLIENT_WRITES = ['clients.create', 'clients.edit', 'clients.status', 'clients.invite'];

    protected const MAY_WRITE_CLIENTS = ['client_relations', 'ceo', 'system_admin'];

    public function up(): void
    {
        if (! DB::table('permissions')->exists()) {
            return;
        }

        $this->addPermission('clients.view.all', 'clients');
        $this->addPermission('teams.view.all', 'teams');

        $this->grant('system_admin', ['clients.view', ...self::CLIENT_WRITES]);

        $this->widen('clients.view', 'clients.view.all', exceptRoles: ['manager']);
        $this->widen('teams.view', 'teams.view.all', exceptRoles: ['team_lead', 'employee']);

        DB::table('role_permissions')
            ->whereIn('permission_id', DB::table('permissions')->whereIn('permission_key', self::CLIENT_WRITES)->select('id'))
            ->whereNotIn('role_id', DB::table('roles')->whereIn('role_key', self::MAY_WRITE_CLIENTS)->select('id'))
            ->delete();

        // It still mentioned leave decisions, which were taken away on 2026-10-28.
        DB::table('roles')->where('role_key', 'manager')->update(['description' => 'Authority over projects and teams.']);
    }

    public function down(): void
    {
        // Deliberately nothing - see 2026_10_28_000001.
    }

    protected function addPermission(string $key, string $module): void
    {
        DB::table('permissions')->insertOrIgnore([
            'permission_key' => $key,
            'permission_name' => "See everybody's - ".$key,
            'module' => $module,
            'is_sensitive' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    protected function grant(string $roleKey, array $permissionKeys): void
    {
        $roleId = DB::table('roles')->where('role_key', $roleKey)->value('id');

        if ($roleId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('permission_key', $permissionKeys)->pluck('id') as $permissionId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    /**
     * @param  list<string>  $exceptRoles
     */
    protected function widen(string $narrowKey, string $wideKey, array $exceptRoles): void
    {
        $wideId = DB::table('permissions')->where('permission_key', $wideKey)->value('id');

        $holders = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('permissions.permission_key', $narrowKey)
            ->whereNotIn('roles.role_key', $exceptRoles)
            ->pluck('role_permissions.role_id');

        foreach ($holders as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $wideId]);
        }
    }
};
