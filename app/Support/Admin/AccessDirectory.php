<?php

namespace App\Support\Admin;

use App\Models\Domain;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Roles, permissions, rank and who holds what — read from the RBAC tables.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE CATALOGUE IS THE `permissions` TABLE, AND NOTHING DERIVES IT ANY MORE
 *
 * The demo source assembled the permission list by reading keys back out of the
 * navigation config and the dashboard registry, because there was no table. §5
 * said that would be replaced by a seeder — it has been, and RbacSeeder is now
 * the one place a key is declared.
 *
 * That matters beyond tidiness. Derivation could only ever see permissions that
 * some sidebar or widget mentioned, so a write permission gating a route and
 * nothing else — `teams.members`, `invoices.manage`, every key in
 * RbacSeeder::MODULE_WRITES — was invisible to the screen that assigns it. The
 * Admin Panel could not grant the permissions the application spent the backend
 * phase adding.
 *
 * `admin.*` and `client.*` are rows in that table and are still absent from
 * `permissions()` here, for the reason §2.1 gives: they are granted by account
 * type, never by a role, and a toggle for one would grant nothing while
 * implying the panel's authority is something a person can be given a piece of.
 *
 * WHAT "SENSITIVE" IS NOW
 *
 * A column on the row (`is_sensitive`), not a list this class keeps. Marking a
 * new permission sensitive is data rather than a deploy — and the mark cannot
 * disagree with the catalogue, because it is on the catalogue.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class AccessDirectory
{
    /**
     * The five domains rank is stored per (§2.5).
     *
     * Rank exists ONLY to answer "may I act on this person" and to route
     * approvals. It never grants a permission — that is entirely
     * role_permissions — which is why this stays separate from everything else.
     *
     * @return array<string, string>
     */
    public static function domains(): array
    {
        return Domain::query()->orderBy('id')->pluck('domain_name', 'domain_key')->all();
    }

    /**
     * Every permission a ROLE may be given, grouped by module.
     *
     * @return array<string, list<array{key: string, label: string, note: string}>>
     */
    public static function permissions(): array
    {
        $grouped = [];

        foreach (self::assignable()->get() as $permission) {
            $grouped[self::moduleLabel($permission->module)][] = [
                'key' => $permission->permission_key,
                'label' => $permission->permission_name,
                'note' => (string) $permission->description,
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * The permissions a role may hold: everything except the two realm bases.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Permission>
     */
    public static function assignable()
    {
        return Permission::query()
            ->whereNotIn('module', ['admin', 'client'])
            ->orderBy('module')
            ->orderBy('permission_key');
    }

    /**
     * @return list<string>
     */
    public static function assignableKeys(): array
    {
        return self::assignable()->pluck('permission_key')->all();
    }

    /**
     * @return list<string>
     */
    public static function sensitive(): array
    {
        return self::assignable()->where('is_sensitive', true)->pluck('permission_key')->all();
    }

    protected static function moduleLabel(string $module): string
    {
        return ucfirst(str_replace('-', ' ', $module));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ROLES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function roles(): Collection
    {
        return Role::query()
            ->with(['permissions', 'domains'])
            ->orderBy('role_name')
            ->get()
            ->map(fn (Role $role) => self::row($role))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function role(string $key): ?array
    {
        $role = Role::query()->with(['permissions', 'domains'])->where('role_key', $key)->first();

        return $role === null ? null : self::row($role);
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Role $role): array
    {
        $ranks = array_fill_keys(array_keys(self::domains()), 0);

        foreach ($role->domains as $domain) {
            $ranks[$domain->domain_key] = (int) $domain->pivot->rank;
        }

        return [
            'key' => $role->role_key,
            'name' => $role->role_name,
            'description' => (string) $role->description,
            'is_active' => (bool) $role->is_active,
            'permissions' => $role->permissions->pluck('permission_key')->all(),
            'ranks' => $ranks,
            'holders' => self::holdersOf($role->role_key),
        ];
    }

    /**
     * Who holds a role.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function holdersOf(string $role): Collection
    {
        return self::accountQuery()
            ->whereHas('roles', fn ($q) => $q->where('role_key', $role))
            ->get()
            ->map(fn (User $user) => AccountDirectory::row($user))
            ->values();
    }

    /**
     * The roles one account holds. Several is normal — they stack (§2.4).
     *
     * @return list<string>
     */
    public static function rolesOf(User $user): array
    {
        return $user->roles()->pluck('role_key')->all();
    }

    /* ══════════════════════════════════════════════════════════════════════
       BLAST RADIUS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * ─────────────────────────────────────────────────────────────────────────
     * WHO A PERMISSION CHANGE WOULD LAND ON.
     *
     * The single most useful thing the Access Control screen shows. Ticking a
     * box on a role is action at a distance: the person doing it is looking at
     * a role NAME, and the consequence lands on people whose names are not on
     * the screen.
     *
     * "Grants salary.view to 4 people: Rahul Mehta, Pooja Singh, …" is the
     * sentence that stops the mistake.
     *
     * People who already hold the permission through ANOTHER role are excluded.
     * Roles stack as a union (§2.4), so granting salary.view to Manager changes
     * nothing for a Manager who is also HR — counting them would overstate the
     * change, and an overstated warning is one people learn to dismiss.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function whoWouldHold(string $permission, string $role): Collection
    {
        $throughOthers = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.permission_key', $permission)
            ->where('roles.role_key', '!=', $role)
            ->where('roles.is_active', true)
            ->pluck('user_roles.user_id');

        return self::accountQuery()
            ->whereHas('roles', fn ($q) => $q->where('role_key', $role))
            ->whereNotIn('users.id', $throughOthers)
            ->get()
            ->map(fn (User $user) => AccountDirectory::row($user))
            ->values();
    }

    /**
     * Everyone who can do something today, however they got it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function whoHolds(string $permission): Collection
    {
        return self::accountQuery()
            ->whereHas('roles', fn ($q) => $q->where('roles.is_active', true)
                ->whereHas('permissions', fn ($p) => $p->where('permission_key', $permission)))
            ->get()
            ->map(fn (User $user) => AccountDirectory::row($user))
            ->values();
    }

    /**
     * The accounts these screens are about.
     *
     * Staff only. A client account holds no roles by construction (§2.2) and
     * the owner's holds none either (§2.1) — listing either would put rows on
     * an access screen that no toggle on it can change.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    protected static function accountQuery()
    {
        return User::query()
            ->where('account_type', \App\Support\Realm::STAFF)
            ->with(['employee.department', 'employee.designation', 'roles'])
            ->orderBy('name');
    }
}
