<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Role;
use App\Models\User;
use App\Support\Admin\AccessDirectory;
use App\Support\IdProof;
use App\Support\Rbac\DesignationAccess;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A designation carries roles and exceptions, and nobody can use one to get
 * round the limits on giving roles (owner, 2026-11-02).
 */
class DesignationAccessTest extends TestCase
{
    protected function designation(string $name = 'Project Manager'): MasterDataItem
    {
        return MasterDataItem::create([
            'list' => MasterDataItem::DESIGNATIONS,
            'name' => $name,
            'code' => strtoupper(substr(md5($name), 0, 8)),
            'is_active' => true,
        ]);
    }

    protected function attach(MasterDataItem $designation, string ...$roleKeys): void
    {
        foreach ($roleKeys as $key) {
            DB::table('designation_roles')->insert([
                'designation_id' => $designation->id,
                'role_id' => Role::where('role_key', $key)->value('id'),
            ]);
        }
    }

    protected function except(MasterDataItem $designation, string $permission, string $effect): void
    {
        DB::table('designation_permissions')->insert([
            'designation_id' => $designation->id,
            'permission_id' => DB::table('permissions')->where('permission_key', $permission)->value('id'),
            'effect' => $effect,
        ]);
    }

    /**
     * @param  list<string>  $roleKeys
     */
    protected function person(?MasterDataItem $designation, array $roleKeys = ['employee'], string $name = 'Asha Roy'): User
    {
        $user = User::factory()->create(['name' => $name, 'account_type' => Realm::STAFF, 'staff_kind' => 'employee']);
        $user->roles()->sync(Role::whereIn('role_key', $roleKeys)->pluck('id'));

        Employee::create([
            'user_id' => $user->id,
            'employment_type' => Employee::FULL_TIME,
            'designation_id' => $designation?->id,
            'joined_on' => now()->subYear()->toDateString(),
        ]);

        return $user;
    }

    /**
     * A complete, valid employee form, so a refusal can only be about the designation.
     *
     * @return array<string, mixed>
     */
    protected function form(int $designationId, string $name = 'Target'): array
    {
        return [
            'name' => $name,
            'personal_email' => strtolower(str_replace(' ', '', $name)).'@gmail.test',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->subMonth()->toDateString(),
            'phone' => '9800000000',
            'current_address' => "1 Somewhere Road\nKolkata",
            'permanent_address' => "2 Elsewhere Lane\nHowrah",
            'id_proof_type' => IdProof::AADHAAR,
            'id_proof_number' => '123456781234',
            'pan' => 'ABCDE1234F',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '50100234567890',
            'basic' => '60000', 'hra' => '24000', 'allowances' => '6000',
            'pf' => '1800', 'pt' => '200', 'tds' => '5000',
            'designation_id' => $designationId,
        ];
    }

    protected function rbac(): Rbac
    {
        $rbac = app(Rbac::class);
        $rbac->forget();

        return $rbac;
    }

    public function test_an_unmapped_designation_changes_nothing(): void
    {
        $plain = $this->person(null, name: 'No Job');
        $titled = $this->person($this->designation(), name: 'Has Job');

        $this->assertEqualsCanonicalizing(
            $this->rbac()->permissionsFor($plain),
            $this->rbac()->permissionsFor($titled),
        );
    }

    public function test_a_designation_passes_on_its_roles_permissions_and_rank(): void
    {
        $job = $this->designation();
        $this->attach($job, 'manager');

        $holder = $this->person($job);
        $rbac = $this->rbac();

        $this->assertTrue($rbac->can($holder, 'projects.create'));
        $this->assertSame(70, $rbac->rankOf($holder, 'work'));
    }

    public function test_never_removes_what_the_designation_grants(): void
    {
        $job = $this->designation();
        $this->attach($job, 'manager');
        $this->except($job, 'projects.publish', DesignationAccess::NEVER);

        $holder = $this->person($job);
        $rbac = $this->rbac();

        $this->assertFalse($rbac->can($holder, 'projects.publish'));
        $this->assertTrue($rbac->can($holder, 'clients.view'));
    }

    public function test_never_cannot_touch_the_employee_base_or_a_role_given_directly(): void
    {
        $job = $this->designation();
        $this->attach($job, 'manager');
        $this->except($job, 'salary.self', DesignationAccess::NEVER);
        $this->except($job, 'announcements.post', DesignationAccess::NEVER);

        $holder = $this->person($job, ['employee', 'hr']);
        $rbac = $this->rbac();

        $this->assertTrue($rbac->can($holder, 'salary.self'));
        $this->assertTrue($rbac->can($holder, 'announcements.post'));
    }

    public function test_always_allow_adds_one_permission_without_a_role(): void
    {
        $job = $this->designation();
        $this->except($job, 'clients.view', DesignationAccess::ALLOW);

        $this->assertTrue($this->rbac()->can($this->person($job), 'clients.view'));
    }

    public function test_a_retired_designation_still_grants_and_a_deactivated_role_does_not(): void
    {
        $job = $this->designation();
        $this->attach($job, 'manager');
        $holder = $this->person($job);

        $job->update(['is_active' => false]);
        $this->assertTrue($this->rbac()->can($holder, 'projects.create'));

        Role::where('role_key', 'manager')->update(['is_active' => false]);
        $rbac = $this->rbac();

        $this->assertFalse($rbac->can($holder, 'projects.create'));
        $this->assertSame(10, $rbac->rankOf($holder, 'work'));
    }

    public function test_a_suspended_holder_has_nothing(): void
    {
        $job = $this->designation();
        $this->attach($job, 'manager');
        $holder = $this->person($job);
        $holder->update(['status' => 'suspended']);

        $this->assertSame([], $this->rbac()->permissionsFor($holder->fresh()));
    }

    public function test_a_mentor_gains_nothing_from_a_record_with_a_designation(): void
    {
        $job = $this->designation();
        $this->attach($job, 'manager');

        $mentor = $this->person($job, ['mentor']);
        $mentor->update(['staff_kind' => 'mentor']);

        $this->assertFalse($this->rbac()->can($mentor->fresh(), 'projects.create'));
    }

    public function test_access_control_counts_designation_holders_honestly(): void
    {
        $job = $this->designation();
        $this->attach($job, 'manager');
        $this->person($job, name: 'Through Job');

        $barred = $this->designation('Product Manager');
        $this->attach($barred, 'manager');
        $this->except($barred, 'salary.view', DesignationAccess::NEVER);
        $this->person($barred, name: 'Barred Job');

        $this->assertContains('Through Job', AccessDirectory::holdersOf('manager')->pluck('name'));
        $this->assertContains('Through Job', AccessDirectory::whoHolds('projects.create')->pluck('name'));

        $reached = AccessDirectory::whoWouldHold('salary.view', 'manager')->pluck('name');

        $this->assertContains('Through Job', $reached);
        $this->assertNotContains('Barred Job', $reached);
    }

    public function test_hr_cannot_give_a_designation_carrying_system_power(): void
    {
        $job = $this->designation('Technical Associate');
        $this->attach($job, 'employee', 'system_admin');

        $staff = $this->person(null, name: 'Target');

        $this->signInAsStaff(['employee', 'hr']);

        $this->assertFalse(DesignationAccess::fitsWithin($job->id, ['manager', 'team_lead']));
        $this->assertTrue(DesignationAccess::fitsWithin($this->designation('Video Editor')->id, []));

        $response = $this->post('/employees/'.$staff->user_id, $this->form($job->id));

        $response->assertSessionHasErrors('designation_id');
        $this->assertNull($staff->employee->fresh()->designation_id);
    }

    public function test_an_always_allow_beyond_what_they_may_give_also_blocks(): void
    {
        $job = $this->designation();
        $this->except($job, 'accounts.manage', DesignationAccess::ALLOW);

        $this->assertFalse(DesignationAccess::fitsWithin($job->id, ['manager']));
        $this->assertTrue(DesignationAccess::fitsWithin($job->id, ['system_admin']));
    }

    public function test_hr_cannot_take_a_system_designation_away_or_set_it_on_a_new_hire_but_can_keep_it(): void
    {
        $top = $this->designation('Technical Associate');
        $this->attach($top, 'system_admin');
        $plain = $this->designation('Video Editor');

        $holder = $this->person($top, name: 'Holder');

        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/'.$holder->user_id, $this->form($plain->id, 'Holder'))
            ->assertSessionHasErrors('designation_id');

        $this->assertSame($top->id, $holder->employee->fresh()->designation_id);

        $this->post('/employees/'.$holder->user_id, $this->form($top->id, 'Holder'))
            ->assertSessionDoesntHaveErrors('designation_id');

        $this->post('/employees', $this->form($top->id, 'New Hire'))
            ->assertSessionHasErrors('designation_id');
    }

    public function test_only_the_owner_sets_a_top_designation_from_accounts(): void
    {
        $top = $this->designation('Technical Associate');
        $this->attach($top, 'system_admin');

        $target = $this->person(null, name: 'Target');
        $sent = ['designation_id' => $top->id, 'joined_on' => now()->subYear()->toDateString()];

        $this->signInAsStaff(['employee', 'system_admin']);

        $this->post('/accounts/'.$target->user_id.'/company', $sent)
            ->assertSessionHasErrors('designation_id');

        $this->assertNull($target->employee->fresh()->designation_id);

        $this->signInAsAdmin();

        $this->post('/admin/accounts/'.$target->user_id.'/company', $sent)
            ->assertSessionHasNoErrors();

        $this->assertSame($top->id, $target->employee->fresh()->designation_id);
    }

    public function test_system_power_through_a_designation_is_told_about_security_holds(): void
    {
        $job = $this->designation('Technical Associate');
        $this->attach($job, 'system_admin');
        $holder = $this->person($job, name: 'Holder');

        $this->assertTrue(User::holdingRole('system_admin')->whereKey($holder->id)->exists());
        $this->assertFalse(User::holdingRole('ceo')->whereKey($holder->id)->exists());
    }

    public function test_the_two_new_roles_exist_and_stay_out_of_employee_records(): void
    {
        $job = $this->designation('Client Relationship Manager');
        $this->attach($job, 'client_relations');
        $holder = $this->person($job);
        $rbac = $this->rbac();

        $this->assertTrue($rbac->can($holder, 'clients.invite'));
        $this->assertFalse($rbac->can($holder, 'employees.view'));
        $this->assertFalse($rbac->can($holder, 'salary.view'));
        $this->assertSame(10, $rbac->rankOf($holder, 'people'));
    }

    public function test_signing_in_and_updates_work_before_the_migration_is_applied(): void
    {
        $job = $this->designation();
        $holder = $this->person($job);

        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_11_02_000003_create_designation_access.php', '--force' => true]);

        $this->assertFalse(DesignationAccess::ready());

        $rbac = $this->rbac();

        $this->assertTrue($rbac->can($holder, 'tasks.view'));
        $this->assertSame(10, $rbac->rankOf($holder, 'people'));
        $this->assertTrue(User::holdingRole('employee')->whereKey($holder->id)->exists());

        $this->actingAs($holder)->get('/dashboard')->assertOk();

        $this->signInAsAdmin();
        $this->get('/admin/updates')->assertOk();
        $this->get('/admin/access')->assertOk();
    }

    public function test_applying_the_migration_adds_the_roles_to_an_existing_database(): void
    {
        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_11_02_000003_create_designation_access.php', '--force' => true]);

        $this->assertFalse(Role::where('role_key', 'client_relations')->exists());

        Artisan::call('migrate', ['--force' => true]);

        $role = Role::where('role_key', 'client_relations')->firstOrFail();

        $this->assertTrue($role->permissions()->where('permission_key', 'clients.invite')->exists());
        $this->assertFalse($role->permissions()->where('permission_key', 'employees.view')->exists());
        $this->assertSame(0, DB::table('designation_roles')->count());
    }
}
