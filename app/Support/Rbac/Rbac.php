<?php

namespace App\Support\Rbac;

use App\Models\User;
use App\Support\Realm;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * The authorisation engine (foundation spec §5).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONE SERVICE, TWO QUESTIONS
 *
 *   can(user, key)                → may they do this at all?
 *   outranks(actor, target, dom)  → may they do it TO THIS PERSON?
 *
 * Every guarded action asks both, plus the self-action check from §2.6. They
 * are separate because they fail differently: an HR executive may hold
 * `leave.approve` and still not be allowed to approve the CEO's leave, and no
 * amount of permission answers that second question.
 *
 * This replaces App\Support\Navigation\PermissiveGate, which allowed everything
 * and threw in production. Every sidebar, every dashboard widget and every
 * client page has been filtering against that placeholder since the shell was
 * built; they now filter against this, and not one of them changes.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * THE THREE RULES THAT ARE NOT PERMISSIONS
 *
 * 1. THE EMPLOYEE BASE IS IMPLICIT. Granted to any staff account of kind
 *    `employee`, from a list in code rather than from `role_permissions`, so
 *    that no role edit can revoke it (§5). A Mentor is staff and does not get
 *    it; that is the whole difference between them.
 *
 * 2. AN INACTIVE ACCOUNT HOLDS NOTHING. Checked before roles are read, so a
 *    suspended account with every role in the system can do nothing at all.
 *
 * 3. REALMS ARE NOT PERMISSIONS. A client holding `salary.view` still cannot
 *    open /salary, because App\Http\Middleware\EnsureRealm refuses the route
 *    before this service is consulted. Two independent barriers, and this one
 *    is the second.
 */
class Rbac
{
    /**
     * The Employee base (§2.2) — my attendance, my leave, my payslip, my
     * tasks, my profile.
     *
     * In code and not in the database on purpose. These are the permissions
     * that make an employee account usable at all, and the failure mode of
     * storing them as a grantable row is somebody removing one and taking
     * everybody's own payslips away in a single click.
     *
     * @var list<string>
     */
    public const EMPLOYEE_BASE = [
        'dashboard.view',
        'profile.view',
        'attendance.self',
        'leave.self',
        'salary.self',
        'tasks.self',
        'teams.self',
        'meetings.self',
    ];

    /**
     * The one role whose holders carry no Employee base.
     *
     * §2.1: "a Mentor is staff and has none of it." The base is granted by
     * `staff_kind` and never by a role, so this constant is NOT how the engine
     * decides anything — `permissionsFor` reads the column, as it must.
     *
     * It exists because a role NAME is sometimes used to stand for the kind of
     * account that holds it: the dashboard's development preview builds "what
     * a Mentor sees" out of a role, and a Mentor with an Employee base is not a
     * Mentor. Written here rather than in that controller so the exception sits
     * beside the rule it qualifies.
     */
    public const NO_EMPLOYEE_BASE_ROLE = 'mentor';

    /**
     * What a client account can reach, by virtue of being one.
     *
     * Implicit for the same reason as the Employee base: these are not a
     * capability pack somebody is given, they are what the account IS. There
     * are no client roles, no client permission screen, and nothing in the
     * Admin Panel that grants or removes any of this — a client either has a
     * portal or does not have an account.
     *
     * Which RECORDS they see is a different question entirely, answered by
     * ownership at the query layer (§6), not by any of these keys.
     *
     * @var list<string>
     */
    public const CLIENT_BASE = [
        'client.dashboard.view',
        'client.projects.view',
        'client.invoices.view',
        'client.tickets.view',
        'client.meetings.view',
        'client.profile.view',
    ];

    /**
     * What the Admin Panel account can reach.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * §2.1: the Admin Panel is "not a role and not assignable".
     *
     * So its capabilities cannot come from `role_permissions` — that table is
     * exactly the mechanism for assigning things to people, and putting
     * `admin.settings.view` in it would make the panel's authority something a
     * person could be given a piece of. It comes from being the admin account,
     * the same way the Employee base comes from being an employee.
     *
     * This is also why these keys are absent from the Access Control screen's
     * assignable list: there is nothing there to grant.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @var list<string>
     */
    public const ADMIN_BASE = [
        'admin.dashboard.view',
        'admin.accounts.view',
        'admin.access.view',
        'admin.master.view',
        'admin.settings.view',
        'admin.audit.view',
        /*
         * The Google connection screen. Its own key rather than folded into
         * `admin.settings.view` — a Drive key must not be reachable by the
         * same grant that changes the brand name (plan doc, "Connecting it",
         * rule 3). It still lives in ADMIN_BASE like everything else here:
         * the realm has one account today, so this is the ceiling until a
         * second admin-realm account exists to actually split CEO from
         * System Administrator (decided 2026-09-17/21).
         */
        'admin.integrations.view',
    ];

    /** @var array<int, list<string>> resolved permissions, per user, per request */
    protected array $cache = [];

    /** @var array<int, array<string, int>> resolved ranks, per user, per request */
    protected array $ranks = [];

    /**
     * May this account do this thing?
     */
    public function can(?Authenticatable $user, string $permission): bool
    {
        if (! $user instanceof User) {
            // Nobody is not somebody. A null user holds nothing — the shell
            // renders an empty sidebar rather than the whole application.
            return false;
        }

        return in_array($permission, $this->permissionsFor($user), true);
    }

    /**
     * Every permission an account holds — the union across its roles, plus the
     * Employee base if it has one (§2.4).
     *
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        /*
         * Rule 2: an account that cannot sign in holds nothing. Checked here
         * as well as at sign-in, because a session issued before a suspension
         * would otherwise keep working until it expired.
         */
        if (! $user->isActive()) {
            return $this->cache[$user->id] = [];
        }

        /*
         * The realm base — what an account can reach by virtue of what it is
         * (§2.1, §2.2). None of it is grantable, and none of it comes from a
         * role. Clients and the admin account hold nothing else.
         */
        $base = match ($user->account_type) {
            Realm::CLIENT => self::CLIENT_BASE,
            Realm::ADMIN => self::ADMIN_BASE,
            default => $user->hasEmployeeBase() ? self::EMPLOYEE_BASE : [],
        };

        $granted = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            // A deactivated role grants nothing while it is off, without having
            // to be unassigned from everybody first.
            ->where('roles.is_active', true)
            ->pluck('permissions.permission_key')
            ->all();

        return $this->cache[$user->id] = array_values(array_unique(array_merge($base, $granted)));
    }

    /**
     * May the actor act on the target, in this domain? (§2.5)
     *
     * ─────────────────────────────────────────────────────────────────────────
     * STRICTLY GREATER, AND EQUAL RANK LOSES.
     *
     * Two HR executives do not get to overrule each other, and two Managers do
     * not get to reassign each other's teams. Peers acting on peers is the
     * situation this check exists to prevent as much as juniors acting on
     * seniors — "no upward action" (§2.6) read literally would permit sideways
     * action, which is not what anybody means by it.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function outranks(?Authenticatable $actor, ?Authenticatable $target, string $domain): bool
    {
        if (! $actor instanceof User || ! $target instanceof User) {
            return false;
        }

        if (! $actor->isActive()) {
            return false;
        }

        return $this->rankOf($actor, $domain) > $this->rankOf($target, $domain);
    }

    /**
     * An account's highest rank in one domain, across all its roles.
     *
     * Highest rather than summed: holding two roles makes somebody more
     * capable, not more senior. Rank is a position, and positions do not add.
     */
    public function rankOf(User $user, string $domain): int
    {
        if (! isset($this->ranks[$user->id])) {
            $this->ranks[$user->id] = DB::table('user_roles')
                ->join('role_domain_rank', 'role_domain_rank.role_id', '=', 'user_roles.role_id')
                ->join('domains', 'domains.id', '=', 'role_domain_rank.domain_id')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $user->id)
                ->where('roles.is_active', true)
                ->groupBy('domains.domain_key')
                ->selectRaw('domains.domain_key, MAX(role_domain_rank.rank) as rank')
                ->pluck('rank', 'domain_key')
                ->map(fn ($rank) => (int) $rank)
                ->all();
        }

        return $this->ranks[$user->id][$domain] ?? 0;
    }

    /**
     * The full check a guarded ACTION needs: permission, rank, and never
     * oneself (§2.6).
     *
     * Offered as one call because the three are always asked together and
     * forgetting the third is the classic version of this bug — the queue that
     * lets somebody approve their own leave.
     */
    public function mayActOn(?Authenticatable $actor, ?Authenticatable $target, string $permission, string $domain): bool
    {
        if (! $actor instanceof User || ! $target instanceof User) {
            return false;
        }

        // Rule 1 of §2.6, and it comes first because it is absolute: nobody
        // approves, edits or deletes their own records. Not even the owner.
        if ($actor->id === $target->id) {
            return false;
        }

        return $this->can($actor, $permission) && $this->outranks($actor, $target, $domain);
    }

    /**
     * Whether an account may enter a realm at all (§3.1).
     *
     * Not a permission: realms are decided by what an account IS, not by what
     * it has been granted. See App\Http\Middleware\EnsureRealm.
     */
    public function belongsToRealm(?Authenticatable $user, string $realm): bool
    {
        return $user instanceof User
            && $user->isActive()
            && $user->account_type === $realm;
    }

    /**
     * Forget what has been resolved.
     *
     * Only needed when roles change inside a single request — the Admin Panel
     * saving a permission, or a test. The cache is per-request, so nothing
     * needs clearing between them.
     */
    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->cache = [];
            $this->ranks = [];

            return;
        }

        unset($this->cache[$user->id], $this->ranks[$user->id]);
    }
}
