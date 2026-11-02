<?php

namespace App\Support\Rbac;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a designation grants (owner, 2026-11-02).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A DESIGNATION INHERITS ITS ROLES, WITH EXCEPTIONS
 *
 * The roles attached to a designation reach everybody holding it, and any one
 * permission can then be set to "always allow" or "never" for that designation.
 *
 * NEVER TRIMS THE DESIGNATION, AND NOTHING ELSE
 *
 * The Employee base still cannot be taken away, and a role given to the person
 * directly still adds whatever it adds (§2.4). So an exception can never lock
 * somebody out of their own payslip, and giving somebody a role by hand always
 * does what it says.
 *
 * ONLY EMPLOYEES HOLD ONE
 *
 * A Mentor or a company account has no job here, whatever its record says, so
 * Rbac::permissionsFor keeps the meaning it has always had for them.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class DesignationAccess
{
    public const ALLOW = 'allow';

    public const NEVER = 'never';

    /** False between uploading this version and applying its migration. */
    public static function ready(): bool
    {
        return Schema::hasTable('designation_permissions');
    }

    public static function designationOf(User $user): ?int
    {
        if (! $user->hasEmployeeBase() || ! self::ready()) {
            return null;
        }

        $id = DB::table('employees')->where('user_id', $user->id)->value('designation_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The permissions somebody holds through their designation.
     *
     * @return list<string>
     */
    public static function grantedTo(User $user, ?string $ignoringRole = null): array
    {
        $designation = self::designationOf($user);

        return $designation === null ? [] : self::grantedBy($designation, $ignoringRole);
    }

    /**
     * @return list<string>
     */
    public static function grantedBy(int $designationId, ?string $ignoringRole = null): array
    {
        $inherited = DB::table('designation_roles')
            ->join('roles', 'roles.id', '=', 'designation_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('designation_roles.designation_id', $designationId)
            ->where('roles.is_active', true)
            ->when($ignoringRole, fn (Builder $query, string $role) => $query->where('roles.role_key', '!=', $role))
            ->pluck('permissions.permission_key');

        $allowed = self::exceptionKeys($designationId, self::ALLOW);
        $never = self::exceptionKeys($designationId, self::NEVER);

        return $inherited->merge($allowed)->unique()->diff($never)->values()->all();
    }

    /**
     * The active roles somebody holds through their designation. Rank reads this.
     *
     * @return list<int>
     */
    public static function roleIdsOf(User $user): array
    {
        $designation = self::designationOf($user);

        if ($designation === null) {
            return [];
        }

        return DB::table('designation_roles')
            ->join('roles', 'roles.id', '=', 'designation_roles.role_id')
            ->where('designation_roles.designation_id', $designation)
            ->where('roles.is_active', true)
            ->pluck('roles.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The users holding a role through their designation, for a whereIn.
     */
    public static function holdersOf(string $roleKey): Builder
    {
        return DB::table('users')
            ->join('employees', 'employees.user_id', '=', 'users.id')
            ->join('designation_roles', 'designation_roles.designation_id', '=', 'employees.designation_id')
            ->join('roles', 'roles.id', '=', 'designation_roles.role_id')
            ->where('users.staff_kind', 'employee')
            ->where('roles.role_key', $roleKey)
            ->select('users.id');
    }

    /** Whether a designation's own "never" would stop this permission reaching them. */
    public static function blocks(User $user, string $permission): bool
    {
        $designation = self::designationOf($user);

        return $designation !== null && self::exceptionKeys($designation, self::NEVER)->contains($permission);
    }

    /**
     * Whether somebody who may give only these roles may hand out this designation.
     *
     * Every role on it must be one they could give by hand, and every "always
     * allow" must be something those roles or the Employee base already hold.
     * Otherwise a designation becomes the way round every limit on giving roles.
     *
     * @param  iterable<string>  $roleKeysTheyMayGive
     */
    public static function fitsWithin(int $designationId, iterable $roleKeysTheyMayGive): bool
    {
        if (! self::ready()) {
            return true;
        }

        $mayGive = collect($roleKeysTheyMayGive)->push('employee')->unique();

        $carried = DB::table('designation_roles')
            ->join('roles', 'roles.id', '=', 'designation_roles.role_id')
            ->where('designation_roles.designation_id', $designationId)
            ->pluck('roles.role_key');

        if ($carried->diff($mayGive)->isNotEmpty()) {
            return false;
        }

        $within = DB::table('roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('roles.role_key', $mayGive)
            ->pluck('permissions.permission_key')
            ->merge(Rbac::EMPLOYEE_BASE);

        return self::exceptionKeys($designationId, self::ALLOW)->diff($within)->isEmpty();
    }

    /**
     * @return Collection<int, string>
     */
    protected static function exceptionKeys(int $designationId, string $effect): Collection
    {
        return DB::table('designation_permissions')
            ->join('permissions', 'permissions.id', '=', 'designation_permissions.permission_id')
            ->where('designation_permissions.designation_id', $designationId)
            ->where('designation_permissions.effect', $effect)
            ->pluck('permissions.permission_key');
    }
}
