<?php

namespace App\Support\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\Realm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Login accounts, for the Admin Panel.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THIS READS `users`, NOT `employees`, AND THE DIFFERENCE IS THE MODULE
 *
 * The demo source listed employment records and hung roles off them, which
 * worked while the two were the same twelve people. They are not the same
 * thing: a Mentor is a staff account with no employment record at all (§2.1),
 * and an employment record whose account has been closed is still an employment
 * record.
 *
 * An accounts screen that starts from `employees` cannot show the first and
 * cannot explain the second. It starts from the account, and the employment
 * record is what it joins ON — absent for some rows, which the page draws.
 *
 * WHY THE OWNER IS NOT IN THE LIST
 *
 * §2.1 makes the Admin Panel a configuration surface with no personal records
 * and no operational authority, operated by one account. That account is the
 * only one that can reach this page. Listing it would offer a suspend button
 * that locks the company out of its own configuration with no way back that
 * does not involve the database — so it is absent from the query, not merely
 * refused by the write. A row nobody may act on is a row that invites the
 * attempt.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class AccountDirectory
{
    /**
     * Every account this panel administers.
     *
     * Staff and clients. Not the owner — see the head of this class.
     *
     * @return Builder<User>
     */
    public static function query(): Builder
    {
        return User::query()
            ->whereNot('account_type', Realm::ADMIN)
            ->with(['employee.department', 'employee.designation', 'roles'])
            ->orderBy('name');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        return self::query()->get()->map(fn (User $user) => self::rowWithRoles($user))->values();
    }

    public static function find(string $userId): ?User
    {
        return self::query()->where('user_id', $userId)->first();
    }

    /**
     * The identity half of an account row.
     *
     * `department` and `designation` come off the employment record and are
     * null where there is none. They are shown here and editable nowhere on
     * this screen — that is HR's, in Employees.
     *
     * @return array<string, mixed>
     */
    public static function row(User $user): array
    {
        return [
            'user_id' => $user->user_id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'account_type' => $user->account_type,
            'staff_kind' => $user->staff_kind,
            'department' => $user->employee?->department?->name,
            'designation' => $user->employee?->designation?->name,
            'last_login_at' => $user->last_login_at,
        ];
    }

    /**
     * The same, with the roles the account holds.
     *
     * @return array<string, mixed>
     */
    public static function rowWithRoles(User $user): array
    {
        return self::row($user) + [
            'roles' => $user->roles
                ->sortBy('role_name')
                ->map(fn (Role $role) => AccessDirectory::row($role))
                ->values(),
        ];
    }

    /**
     * The headline counts.
     *
     * `no_roles` is the one worth looking at, and it is why this card exists: a
     * staff account that can sign in and holds nothing is somebody who was
     * created and then forgotten, and they will not report it — from the
     * inside it looks like an application with no modules in it.
     *
     * Client accounts are excluded from that count rather than swelling it:
     * they hold no roles by construction (§2.2), so counting them would report
     * a loose end for every client the company has.
     *
     * @return array<string, int>
     */
    public static function stats(): array
    {
        $staff = self::query()->where('account_type', Realm::STAFF);

        return [
            'total' => self::query()->count(),
            'active' => (clone $staff)->where('status', 'active')->count(),
            'inactive' => (clone $staff)->whereNot('status', 'active')->count(),
            'no_roles' => (clone $staff)->whereDoesntHave('roles')->count(),
        ];
    }
}
