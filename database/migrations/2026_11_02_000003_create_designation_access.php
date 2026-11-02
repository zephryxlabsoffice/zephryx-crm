<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a designation grants: its roles, and its exceptions (owner, 2026-11-02).
 * Both tables start empty, so nobody's access changes when this is applied. See
 * App\Support\Rbac\DesignationAccess.
 *
 * Two roles are added with nobody holding them: Client Relations, so that job
 * never inherits HR's future features, and Accounts.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, description: string, keys: list<string>}> */
    protected const ROLES = [
        'client_relations' => [
            'name' => 'Client Relations',
            'description' => 'Clients and nothing about employees.',
            'keys' => ['clients.view', 'clients.create', 'clients.edit', 'clients.status', 'clients.invite'],
        ],
        'accounts' => [
            'name' => 'Accounts',
            'description' => 'Invoices. Salary stays with HR.',
            'keys' => ['invoices.view', 'invoices.manage'],
        ],
    ];

    protected const STAFF_READING = [
        'tasks.view', 'teams.view', 'projects.view', 'meetings.view',
        'tickets.view', 'leave.view', 'announcements.view', 'calendar.view',
    ];

    public function up(): void
    {
        Schema::create('designation_roles', function (Blueprint $table) {
            $table->foreignId('designation_id')->constrained('master_data_items')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['designation_id', 'role_id']);
        });

        Schema::create('designation_permissions', function (Blueprint $table) {
            $table->foreignId('designation_id')->constrained('master_data_items')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('effect', 8);
            $table->primary(['designation_id', 'permission_id']);
        });

        // A database with no permissions yet is about to be seeded, which adds these.
        if (! DB::table('permissions')->exists()) {
            return;
        }

        $this->addRoles();
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_permissions');
        Schema::dropIfExists('designation_roles');

        DB::table('roles')->whereIn('role_key', array_keys(self::ROLES))->delete();
    }

    protected function addRoles(): void
    {
        $domains = DB::table('domains')->pluck('id', 'domain_key');

        foreach (self::ROLES as $key => $role) {
            DB::table('roles')->insertOrIgnore([
                'role_key' => $key,
                'role_name' => $role['name'],
                'description' => $role['description'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleId = DB::table('roles')->where('role_key', $key)->value('id');

            $permissions = DB::table('permissions')
                ->whereIn('permission_key', [...self::STAFF_READING, ...$role['keys']])
                ->pluck('id');

            foreach ($permissions as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }

            foreach (['people', 'work', 'support', 'system'] as $domain) {
                if (isset($domains[$domain])) {
                    DB::table('role_domain_rank')->insertOrIgnore([
                        'role_id' => $roleId,
                        'domain_id' => $domains[$domain],
                        'rank' => 10,
                    ]);
                }
            }
        }
    }
};
