<?php

namespace App\Support\Rbac;

use App\Models\Employee;
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
        $settings = self::settingsOf($designationId);

        return self::grantsOf(
            array_values(array_diff($settings['roles'], [$ignoringRole])),
            $settings['exceptions'],
        );
    }

    /**
     * What these settings would grant, whether saved or only proposed.
     *
     * @param  list<string>  $roleKeys
     * @param  array<string, string>  $exceptions
     * @return list<string>
     */
    public static function grantsOf(array $roleKeys, array $exceptions): array
    {
        $inherited = DB::table('roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('roles.role_key', $roleKeys)
            ->where('roles.is_active', true)
            ->pluck('permissions.permission_key');

        $effects = collect($exceptions);

        return $inherited
            ->merge($effects->filter(fn (string $effect) => $effect === self::ALLOW)->keys())
            ->unique()
            ->diff($effects->filter(fn (string $effect) => $effect === self::NEVER)->keys())
            ->values()
            ->all();
    }

    /**
     * @return array{roles: list<string>, exceptions: array<string, string>}
     */
    public static function settingsOf(int $designationId): array
    {
        return [
            'roles' => DB::table('designation_roles')
                ->join('roles', 'roles.id', '=', 'designation_roles.role_id')
                ->where('designation_roles.designation_id', $designationId)
                ->pluck('roles.role_key')
                ->all(),
            'exceptions' => DB::table('designation_permissions')
                ->join('permissions', 'permissions.id', '=', 'designation_permissions.permission_id')
                ->where('designation_permissions.designation_id', $designationId)
                ->pluck('designation_permissions.effect', 'permissions.permission_key')
                ->all(),
        ];
    }

    /**
     * Who holding this designation would gain or lose what, under these settings.
     *
     * A permission held through a role given directly never moves, and the Employee
     * base is out of it, so each list is what would really change for that person.
     *
     * @param  array{roles: list<string>, exceptions: array<string, string>}  $settings
     * @return list<array{name: string, gains: list<string>, loses: list<string>, still_held: list<string>, redundant_roles: list<string>}>
     */
    public static function changesFor(int $designationId, array $settings): array
    {
        return self::changesForPeople(
            Employee::with('user.roles')->where('designation_id', $designationId)->get(),
            $settings,
        );
    }

    /**
     * @param  iterable<Employee>  $employees
     * @param  array{roles: list<string>, exceptions: array<string, string>}  $settings
     * @return list<array{name: string, gains: list<string>, loses: list<string>, still_held: list<string>, redundant_roles: list<string>}>
     */
    public static function changesForPeople(iterable $employees, array $settings): array
    {
        $rbac = app(Rbac::class);
        $proposed = self::grantsOf($settings['roles'], $settings['exceptions']);
        $never = array_keys(array_filter($settings['exceptions'], fn (string $effect) => $effect === self::NEVER));
        $changes = [];

        foreach ($employees as $employee) {
            $user = $employee->user;

            if ($user === null || ! $user->hasEmployeeBase() || ! $user->isActive()) {
                continue;
            }

            $direct = $rbac->directlyGrantedFor($user);
            $now = array_diff($rbac->grantedFor($user), Rbac::EMPLOYEE_BASE);
            $then = array_diff(array_merge($direct, $proposed), Rbac::EMPLOYEE_BASE);

            $changes[] = [
                'name' => (string) $user->name,
                'gains' => array_values(array_unique(array_diff($then, $now))),
                'loses' => array_values(array_unique(array_diff($now, $then))),
                'still_held' => array_values(array_intersect($never, $direct)),
                'redundant_roles' => array_values(array_intersect(
                    array_diff($settings['roles'], ['employee']),
                    $user->roles->pluck('role_key')->all(),
                )),
            ];
        }

        return $changes;
    }

    /**
     * @return list<string>
     */
    public static function roleNamesOf(int $designationId): array
    {
        return DB::table('designation_roles')
            ->join('roles', 'roles.id', '=', 'designation_roles.role_id')
            ->where('designation_roles.designation_id', $designationId)
            ->orderBy('roles.role_name')
            ->pluck('roles.role_name')
            ->all();
    }

    public static function holdersCount(int $designationId): int
    {
        return Employee::where('designation_id', $designationId)->count();
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
