<?php

namespace Tests\Feature;

use App\Mail\AccountInviteMail;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\Admin\AccessDirectory;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 2026-10-27: the Admin Panel can add a staff account (so a fresh install can
 * get its first HR), and a new role takes its level from an existing role
 * instead of five typed numbers.
 */
class AdminAccountsAndRolesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->signInAsAdmin();
    }

    /* ── adding a staff account ── */

    public function test_the_owner_can_add_the_first_hr_account_and_it_gets_the_invite(): void
    {
        $this->get('/admin/accounts')->assertOk()->assertSee('Add account');
        $this->get('/admin/accounts/new')->assertOk()->assertSee('Add and send invite');

        $response = $this->post('/admin/accounts', [
            'name' => 'Priya Nair',
            'personal_email' => 'priya@zephryxlabs.test',
            'phone' => '9800000001',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->subMonth()->toDateString(),
            'roles' => ['hr'],
        ]);

        $user = User::where('email', 'priya@zephryxlabs.test')->firstOrFail();

        $response->assertRedirect('/admin/accounts/'.$user->user_id);

        $this->assertSame('staff', $user->account_type);
        $this->assertEqualsCanonicalizing(['employee', 'hr'], $user->roles()->pluck('role_key')->all());
        $this->assertNotNull(Employee::where('user_id', $user->id)->first());

        Mail::assertSent(AccountInviteMail::class, fn ($mail) => $mail->hasTo('priya@zephryxlabs.test'));

        // And the new HR account can then add employees itself.
        $this->assertTrue(app(Rbac::class)->can($user->fresh(), 'employees.create'));
    }

    public function test_the_employee_role_is_always_given(): void
    {
        $this->post('/admin/accounts', [
            'name' => 'No Roles Ticked',
            'personal_email' => 'plain@zephryxlabs.test',
            'phone' => '9800000002',
            'employment_type' => Employee::INTERN,
            'joined_on' => now()->toDateString(),
        ])->assertRedirect();

        $user = User::where('email', 'plain@zephryxlabs.test')->firstOrFail();

        $this->assertSame(['employee'], $user->roles()->pluck('role_key')->all());
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        $this->post('/admin/accounts', [
            'name' => 'Twice',
            'personal_email' => 'twice@zephryxlabs.test',
            'phone' => '9800000003',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->toDateString(),
        ]);

        $this->post('/admin/accounts', [
            'name' => 'Twice Again',
            'personal_email' => 'twice@zephryxlabs.test',
            'phone' => '9800000003',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->toDateString(),
        ])->assertSessionHasErrors('personal_email');

        $this->assertSame(1, User::where('email', 'twice@zephryxlabs.test')->count());
    }

    /* ── a normal account: roles, and no employment ── */

    public function test_the_owner_can_add_a_normal_account_that_is_not_an_employee(): void
    {
        $this->get('/admin/accounts/new?kind=normal')->assertOk()->assertSee('Normal account');

        $this->post('/admin/accounts', [
            'kind' => 'normal',
            'name' => 'HR Desk',
            'account_email' => 'hr@zephryxlabs.test',
            'roles' => ['hr'],
        ])->assertRedirect();

        $user = User::where('email', 'hr@zephryxlabs.test')->firstOrFail();

        $this->assertStringStartsWith('ZEPH6', $user->user_id);
        $this->assertNull($user->staff_kind);
        $this->assertFalse($user->hasEmployeeBase());
        $this->assertNull(Employee::where('user_id', $user->id)->first());
        $this->assertSame(['hr'], $user->roles()->pluck('role_key')->all());

        $rbac = app(Rbac::class);
        $this->assertTrue($rbac->can($user, 'employees.create'));
        $this->assertFalse($rbac->can($user, 'attendance.self'));

        Mail::assertSent(AccountInviteMail::class, fn ($mail) => $mail->hasTo('hr@zephryxlabs.test'));

        $this->get('/admin/accounts')->assertOk()->assertSee('HR Desk');
        $this->get('/admin/accounts/'.$user->user_id)->assertOk()->assertSee('HR Desk');
    }

    public function test_a_normal_account_needs_a_role(): void
    {
        $this->post('/admin/accounts', [
            'kind' => 'normal',
            'name' => 'Nothing',
            'account_email' => 'nothing@zephryxlabs.test',
        ])->assertSessionHasErrors('roles');
    }

    public function test_a_normal_hr_account_can_open_every_page_in_its_menu(): void
    {
        $this->post('/admin/accounts', [
            'kind' => 'normal',
            'name' => 'HR Desk',
            'account_email' => 'hr@zephryxlabs.test',
            'roles' => ['hr'],
        ]);

        $user = User::where('email', 'hr@zephryxlabs.test')->firstOrFail();
        $this->actingAs($user);

        $rbac = app(Rbac::class);
        $opened = 0;

        foreach ((array) config('navigation') as $item) {
            if (! isset($item['route']) || ! \Illuminate\Support\Facades\Route::has($item['route'])) {
                continue;
            }

            if (isset($item['permission']) && ! $rbac->can($user, $item['permission'])) {
                continue;
            }

            $status = $this->get(route($item['route']))->getStatusCode();

            $this->assertLessThan(500, $status, $item['route'].' errored for a normal account');
            $this->assertNotSame(403, $status, $item['route'].' is in the menu but refused');
            $opened++;
        }

        $this->assertGreaterThan(5, $opened);
    }

    /* ── company details from the panel ── */

    public function test_the_owner_can_set_department_designation_and_reporting_line(): void
    {
        foreach (['boss@zephryxlabs.test' => 'Boss Person', 'staff@zephryxlabs.test' => 'Staff Person'] as $email => $name) {
            $this->post('/admin/accounts', [
                'name' => $name, 'personal_email' => $email, 'phone' => '9800000009',
                'employment_type' => Employee::FULL_TIME, 'joined_on' => now()->subYear()->toDateString(),
            ]);
        }

        $boss = Employee::whereHas('user', fn ($q) => $q->where('email', 'boss@zephryxlabs.test'))->firstOrFail();
        $staff = Employee::with('user')->whereHas('user', fn ($q) => $q->where('email', 'staff@zephryxlabs.test'))->firstOrFail();

        $dept = \App\Models\MasterDataItem::inList(\App\Models\MasterDataItem::DEPARTMENTS)->active()->firstOrFail();
        $desig = \App\Models\MasterDataItem::inList(\App\Models\MasterDataItem::DESIGNATIONS)->active()->firstOrFail();

        $this->get('/admin/accounts/'.$staff->user->user_id)->assertOk()->assertSee('Company details');

        $this->post('/admin/accounts/'.$staff->user->user_id.'/company', [
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'reports_to' => $boss->id,
            'joined_on' => '2025-06-01',
        ])->assertRedirect('/admin/accounts/'.$staff->user->user_id);

        $staff->refresh();

        $this->assertSame($dept->id, $staff->department_id);
        $this->assertSame($desig->id, $staff->designation_id);
        $this->assertSame($boss->id, $staff->reports_to);
        $this->assertSame('2025-06-01', $staff->joined_on->toDateString());

        // Nobody reports to themselves.
        $this->post('/admin/accounts/'.$staff->user->user_id.'/company', [
            'reports_to' => $staff->id, 'joined_on' => '2025-06-01',
        ])->assertSessionHasErrors('reports_to');
    }

    public function test_a_normal_account_has_no_company_details(): void
    {
        $this->post('/admin/accounts', [
            'kind' => 'normal', 'name' => 'HR Desk', 'account_email' => 'hr@zephryxlabs.test', 'roles' => ['hr'],
        ]);

        $user = User::where('email', 'hr@zephryxlabs.test')->firstOrFail();

        $this->get('/admin/accounts/'.$user->user_id)->assertOk()->assertDontSee('Company details');
        $this->post('/admin/accounts/'.$user->user_id.'/company', ['joined_on' => '2025-01-01'])->assertNotFound();
    }

    /* ── a role at the same level as another ── */

    public function test_a_new_role_copies_the_rank_of_the_role_it_sits_beside(): void
    {
        $this->post('/admin/access', [
            'role_name' => 'Senior Designer',
            'level_as' => 'team_lead',
            'permissions' => ['tasks.view'],
        ])->assertRedirect('/admin/access/senior_designer');

        $this->assertSame(
            AccessDirectory::role('team_lead')['ranks'],
            AccessDirectory::role('senior_designer')['ranks'],
        );
    }

    public function test_starting_from_a_role_ticks_its_permissions(): void
    {
        $hr = AccessDirectory::role('hr');

        $response = $this->get('/admin/access/create?from=hr')->assertOk();

        foreach (array_slice($hr['permissions'], 0, 3) as $key) {
            $response->assertSee('value="'.$key.'"'."\n".'                                               checked', false);
        }
    }

    public function test_a_duplicate_name_is_refused_in_plain_words(): void
    {
        $this->post('/admin/access', ['role_name' => 'HR'])
            ->assertSessionHasErrors(['role_key' => 'A role with this name already exists. Choose a different name.']);
    }
}
