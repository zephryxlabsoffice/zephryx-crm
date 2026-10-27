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
        $this->get('/admin/accounts')->assertOk()->assertSee('Add staff account');
        $this->get('/admin/accounts/new')->assertOk()->assertSee('Add and send invite');

        $response = $this->post('/admin/accounts', [
            'name' => 'Priya Nair',
            'email' => 'priya@zephryxlabs.test',
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
            'email' => 'plain@zephryxlabs.test',
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
            'email' => 'twice@zephryxlabs.test',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->toDateString(),
        ]);

        $this->post('/admin/accounts', [
            'name' => 'Twice Again',
            'email' => 'twice@zephryxlabs.test',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->toDateString(),
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'twice@zephryxlabs.test')->count());
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
