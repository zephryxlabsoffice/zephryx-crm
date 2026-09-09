<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Tests\TestCase;

/**
 * The authorisation engine (foundation spec §5).
 *
 * The rules worth guarding are the ones that are not permissions: the implicit
 * bases, the union across stacked roles, and the three checks a guarded action
 * has to make together.
 */
class RbacTest extends TestCase
{
    protected Rbac $rbac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rbac = app(Rbac::class);
    }

    protected function staff(array $roles = [], string $kind = 'employee', string $status = 'active'): User
    {
        $user = User::factory()->create([
            'account_type' => Realm::STAFF,
            'staff_kind' => $kind,
            'status' => $status,
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        $this->rbac->forget();

        return $user;
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE UNION (§2.4)
       ══════════════════════════════════════════════════════════════════════ */

    public function test_roles_stack_and_nothing_is_lost_by_gaining_one(): void
    {
        $employee = $this->staff(['employee']);
        $both = $this->staff(['employee', 'hr']);

        // Everything the narrower account has, the wider one still has.
        foreach ($this->rbac->permissionsFor($employee) as $permission) {
            $this->assertTrue(
                $this->rbac->can($both, $permission),
                'Employee + HR lost '.$permission.' that Employee alone holds',
            );
        }

        // Plus what HR adds.
        $this->assertTrue($this->rbac->can($both, 'salary.view'));
        $this->assertFalse($this->rbac->can($employee, 'salary.view'));
    }

    public function test_there_is_no_way_for_a_role_to_take_something_away(): void
    {
        /*
         * `role_permissions` is a plain join with no deny row and no precedence
         * column, so union resolution needs no ordering. The moment a deny
         * exists, every question becomes "which rule won".
         *
         * Asserted against the schema rather than behaviour, because the point
         * is that the shape makes it impossible.
         */
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('role_permissions');

        sort($columns);

        $this->assertSame(['permission_id', 'role_id'], $columns);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE IMPLICIT BASES (§2.1, §2.2, §5)
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_employee_base_comes_from_the_account_not_from_a_role(): void
    {
        // No roles at all, and still holds their own attendance and payslips.
        $user = $this->staff([]);

        foreach (Rbac::EMPLOYEE_BASE as $permission) {
            $this->assertTrue($this->rbac->can($user, $permission), $permission.' is not in the Employee base');
        }
    }

    public function test_no_role_edit_can_revoke_the_employee_base(): void
    {
        /*
         * §5 is explicit that the base is granted implicitly so it "can never
         * be accidentally revoked by role edits". This is that, tested: strip
         * every permission from every role and an employee still holds their
         * own records.
         */
        $user = $this->staff(['employee', 'hr']);

        foreach (Role::all() as $role) {
            $role->permissions()->detach();
        }

        $this->rbac->forget();

        $this->assertTrue($this->rbac->can($user, 'attendance.self'));
        $this->assertTrue($this->rbac->can($user, 'salary.self'));
        // And the granted ones are gone, so the detach really happened.
        $this->assertFalse($this->rbac->can($user, 'salary.view'));
    }

    public function test_a_mentor_is_staff_and_has_no_employee_base(): void
    {
        /*
         * §2.1 — read-only, no personal records at all. One column does the
         * whole job: `staff_kind`. A Mentor holding the mentor role still gets
         * nothing personal.
         */
        $mentor = $this->staff(['mentor'], kind: 'mentor');

        $this->assertFalse($mentor->hasEmployeeBase());

        foreach (['attendance.self', 'leave.self', 'salary.self', 'tasks.self'] as $personal) {
            $this->assertFalse($this->rbac->can($mentor, $personal), 'a Mentor holds '.$personal);
        }

        // What they do keep: read-only sight of the company.
        $this->assertTrue($this->rbac->can($mentor, 'projects.view'));
    }

    public function test_a_client_holds_the_client_base_and_nothing_of_the_staff_side(): void
    {
        $client = User::factory()->create([
            'account_type' => Realm::CLIENT,
            'staff_kind' => null,
            // A reference, not a name — see the `clients` migration.
            'client_ref' => 'CLT001',
        ]);

        foreach (Rbac::CLIENT_BASE as $permission) {
            $this->assertTrue($this->rbac->can($client, $permission));
        }

        foreach (['salary.view', 'employees.view', 'attendance.self', 'dashboard.view'] as $staffKey) {
            $this->assertFalse($this->rbac->can($client, $staffKey), 'a client holds '.$staffKey);
        }
    }

    public function test_the_admin_base_is_not_grantable_to_anybody(): void
    {
        /*
         * §2.1: the Admin Panel is "not a role and not assignable". Its keys
         * come from being the admin account, exactly as the Employee base comes
         * from being an employee — so no role may carry one.
         */
        $adminKeys = Permission::whereIn('permission_key', Rbac::ADMIN_BASE)->pluck('id');

        $this->assertNotEmpty($adminKeys, 'the admin permissions were never seeded');

        $granted = \Illuminate\Support\Facades\DB::table('role_permissions')
            ->whereIn('permission_id', $adminKeys)
            ->count();

        $this->assertSame(0, $granted, 'a role has been given an Admin Panel permission');

        // And the owner holds them without holding any role.
        $owner = User::factory()->create(['account_type' => Realm::ADMIN, 'staff_kind' => null]);

        $this->assertCount(0, $owner->roles);
        $this->assertTrue($this->rbac->can($owner, 'admin.settings.view'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       RANK (§2.5)
       ══════════════════════════════════════════════════════════════════════ */

    public function test_rank_is_per_domain_and_hr_outranks_system_admin_in_people(): void
    {
        /*
         * The row that makes rank a table rather than a `hierarchy_level`
         * column. HR outranks the System Administrator in people and finance
         * and is outranked in system — one integer cannot say that.
         */
        $hr = $this->staff(['employee', 'hr']);
        $sysadmin = $this->staff(['employee', 'system_admin']);

        $this->assertTrue($this->rbac->outranks($hr, $sysadmin, Domain::PEOPLE));
        $this->assertTrue($this->rbac->outranks($hr, $sysadmin, Domain::FINANCE));

        $this->assertTrue($this->rbac->outranks($sysadmin, $hr, Domain::SYSTEM));
        $this->assertFalse($this->rbac->outranks($hr, $sysadmin, Domain::SYSTEM));
    }

    public function test_equal_rank_does_not_outrank(): void
    {
        /*
         * Two HR executives do not get to overrule each other. "No upward
         * action" read literally would permit sideways action, which is not
         * what anybody means by it.
         */
        $one = $this->staff(['employee', 'hr']);
        $two = $this->staff(['employee', 'hr']);

        $this->assertFalse($this->rbac->outranks($one, $two, Domain::PEOPLE));
        $this->assertFalse($this->rbac->outranks($two, $one, Domain::PEOPLE));
    }

    public function test_holding_two_roles_makes_somebody_more_capable_not_more_senior(): void
    {
        // Rank is a position, and positions do not add.
        $stacked = $this->staff(['employee', 'hr', 'manager']);
        $ceo = $this->staff(['employee', 'ceo']);

        $this->assertSame(80, $this->rbac->rankOf($stacked, Domain::PEOPLE));
        $this->assertTrue($this->rbac->outranks($ceo, $stacked, Domain::PEOPLE));
    }

    public function test_a_mentor_has_no_standing_anywhere(): void
    {
        $mentor = $this->staff(['mentor'], kind: 'mentor');
        $intern = $this->staff(['employee']);

        foreach ([Domain::PEOPLE, Domain::FINANCE, Domain::WORK, Domain::SUPPORT, Domain::SYSTEM] as $domain) {
            $this->assertSame(0, $this->rbac->rankOf($mentor, $domain));
            $this->assertFalse($this->rbac->outranks($mentor, $intern, $domain));
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE THREE CHECKS A GUARDED ACTION MAKES (§2.6)
       ══════════════════════════════════════════════════════════════════════ */

    public function test_nobody_acts_on_their_own_records_however_senior(): void
    {
        /*
         * Rule 1 of §2.6, and it is absolute. The CEO holds `leave.approve` and
         * outranks everybody, and still cannot approve their own leave.
         *
         * This is the check that gets forgotten, because permission and rank
         * both say yes.
         */
        $ceo = $this->staff(['employee', 'ceo']);

        $this->assertTrue($this->rbac->can($ceo, 'leave.approve'));
        $this->assertFalse($this->rbac->mayActOn($ceo, $ceo, 'leave.approve', Domain::PEOPLE));
    }

    public function test_permission_without_rank_is_not_enough(): void
    {
        // HR holds leave.approve and does not outrank the CEO in people.
        $hr = $this->staff(['employee', 'hr']);
        $ceo = $this->staff(['employee', 'ceo']);

        $this->assertTrue($this->rbac->can($hr, 'leave.approve'));
        $this->assertFalse($this->rbac->mayActOn($hr, $ceo, 'leave.approve', Domain::PEOPLE));

        // The other way round works.
        $employee = $this->staff(['employee']);
        $this->assertTrue($this->rbac->mayActOn($hr, $employee, 'leave.approve', Domain::PEOPLE));
    }

    public function test_rank_without_permission_is_not_enough_either(): void
    {
        // A Manager outranks an employee in people and does not hold
        // attendance.reject — rank never grants anything (§2.5).
        $manager = $this->staff(['employee', 'manager']);
        $employee = $this->staff(['employee']);

        $this->assertTrue($this->rbac->outranks($manager, $employee, Domain::PEOPLE));
        $this->assertFalse($this->rbac->can($manager, 'attendance.reject'));
        $this->assertFalse($this->rbac->mayActOn($manager, $employee, 'attendance.reject', Domain::PEOPLE));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE EDGES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_nobody_holds_nothing(): void
    {
        $this->assertFalse($this->rbac->can(null, 'dashboard.view'));
        $this->assertFalse($this->rbac->outranks(null, null, Domain::PEOPLE));
        $this->assertFalse($this->rbac->belongsToRealm(null, Realm::STAFF));
    }

    public function test_a_deactivated_role_grants_nothing_without_being_unassigned(): void
    {
        $user = $this->staff(['employee', 'hr']);

        $this->assertTrue($this->rbac->can($user, 'salary.view'));

        Role::where('role_key', 'hr')->update(['is_active' => false]);
        $this->rbac->forget();

        $this->assertFalse($this->rbac->can($user, 'salary.view'));
        // The Employee base is untouched: it never came from a role.
        $this->assertTrue($this->rbac->can($user, 'salary.self'));
    }

    public function test_realm_membership_is_not_a_permission(): void
    {
        /*
         * A client holding a staff key by whatever mistake still does not
         * belong to the staff realm — the two questions are answered by
         * different things, and EnsureRealm asks this one before any data is
         * read.
         */
        $client = User::factory()->create(['account_type' => Realm::CLIENT, 'staff_kind' => null]);

        $this->assertFalse($this->rbac->belongsToRealm($client, Realm::STAFF));
        $this->assertTrue($this->rbac->belongsToRealm($client, Realm::CLIENT));
    }

    public function test_every_permission_a_page_asks_for_exists_as_a_row(): void
    {
        /*
         * The seeder derives the permission table from the sidebars and the
         * dashboard registry, so a key cannot be used by a page and missing
         * from the table that grants it — the failure that would otherwise make
         * a module silently invisible to everybody.
         */
        $seeded = Permission::pluck('permission_key');

        foreach (['navigation', 'navigation-client', 'navigation-admin'] as $file) {
            foreach ((array) config($file) as $entry) {
                $this->assertTrue($seeded->contains($entry['permission']), $entry['permission'].' has no row');
            }
        }

        foreach (array_merge((array) config('dashboard.kpis'), (array) config('dashboard.widgets')) as $entry) {
            $this->assertTrue($seeded->contains($entry['permission']), $entry['permission'].' has no row');
        }

        foreach (array_merge(Rbac::EMPLOYEE_BASE, Rbac::CLIENT_BASE, Rbac::ADMIN_BASE) as $key) {
            $this->assertTrue($seeded->contains($key), $key.' has no row');
        }
    }

    public function test_re_seeding_never_revokes_anything(): void
    {
        /*
         * A seeder that silently removed a grant on deploy would be invisible
         * until somebody needed the thing it took away.
         */
        $before = Role::where('role_key', 'hr')->first()->permissions()->pluck('permission_key')->sort()->values();

        $this->seed(\Database\Seeders\RbacSeeder::class);

        $after = Role::where('role_key', 'hr')->first()->permissions()->pluck('permission_key')->sort()->values();

        $this->assertSame($before->all(), $after->all());
    }
}
