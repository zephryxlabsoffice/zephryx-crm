<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The owner attaches roles and exceptions to a designation after seeing who gains
 * and loses what (owner, 2026-11-02).
 */
class DesignationAccessScreenTest extends TestCase
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

    /**
     * @param  list<string>  $roleKeys
     */
    protected function person(MasterDataItem $designation, array $roleKeys = ['employee'], string $name = 'Asha Roy'): User
    {
        $user = User::factory()->create(['name' => $name, 'account_type' => Realm::STAFF, 'staff_kind' => 'employee']);
        $user->roles()->sync(Role::whereIn('role_key', $roleKeys)->pluck('id'));

        Employee::create([
            'user_id' => $user->id,
            'employment_type' => Employee::FULL_TIME,
            'designation_id' => $designation->id,
            'joined_on' => now()->subYear()->toDateString(),
        ]);

        return $user;
    }

    protected function holds(User $user, string $permission): bool
    {
        $rbac = app(Rbac::class);
        $rbac->forget();

        return $rbac->can($user, $permission);
    }

    public function test_the_preview_names_who_gains_and_saves_nothing(): void
    {
        $this->signInAsAdmin();

        $job = $this->designation();
        $holder = $this->person($job);

        $this->post("/admin/master-data/designations/{$job->id}/access/preview", ['roles' => ['manager']])
            ->assertOk()
            ->assertSee('Asha Roy')
            ->assertSee('Gains')
            ->assertDontSee('Loses');

        $this->assertSame(0, DB::table('designation_roles')->count());
        $this->assertFalse($this->holds($holder, 'projects.create'));
    }

    public function test_saving_grants_the_access_and_audits_who_it_reached(): void
    {
        $this->signInAsAdmin();

        $job = $this->designation();
        $holder = $this->person($job);

        $this->post("/admin/master-data/designations/{$job->id}/access", [
            'roles' => ['manager'],
            'exceptions' => ['projects.publish' => 'never', 'tasks.view' => 'inherit'],
        ])->assertRedirect("/admin/master-data/designations/{$job->id}/access");

        $this->assertTrue($this->holds($holder, 'projects.create'));
        $this->assertFalse($this->holds($holder, 'projects.publish'));
        $this->assertSame(1, DB::table('designation_permissions')->count());

        $entry = DB::table('audit_log')->where('action', AuditLog::PERMISSION_CHANGED)->latest('id')->first();

        $this->assertSame('designation', $entry->entity_type);
        $this->assertStringContainsString('never projects.publish', $entry->after_json);
        $this->assertStringContainsString('Asha Roy', $entry->after_json);
        $this->assertStringContainsString('no roles', $entry->before_json);
    }

    public function test_removing_a_role_shows_the_loss_and_takes_it_away(): void
    {
        $this->signInAsAdmin();

        $job = $this->designation();
        $holder = $this->person($job);

        $this->post("/admin/master-data/designations/{$job->id}/access", ['roles' => ['manager']]);
        $this->assertTrue($this->holds($holder, 'projects.create'));

        $this->post("/admin/master-data/designations/{$job->id}/access/preview", [])
            ->assertOk()
            ->assertSee('Loses');

        $this->post("/admin/master-data/designations/{$job->id}/access", []);
        $this->assertFalse($this->holds($holder, 'projects.create'));
    }

    public function test_the_preview_says_when_a_direct_role_defeats_never_or_is_now_redundant(): void
    {
        $this->signInAsAdmin();

        $job = $this->designation();
        $this->person($job, ['employee', 'hr'], 'Direct HR');
        $this->person($job, ['employee', 'manager'], 'Direct Manager');

        $response = $this->post("/admin/master-data/designations/{$job->id}/access/preview", [
            'roles' => ['manager'],
            'exceptions' => ['projects.publish' => 'never'],
        ])->assertOk();

        $response->assertSee('Never does not apply to them');
        $response->assertSee('no longer needed');
    }

    public function test_saving_the_same_settings_changes_nothing(): void
    {
        $this->signInAsAdmin();

        $job = $this->designation();

        $this->post("/admin/master-data/designations/{$job->id}/access/preview", [])
            ->assertRedirect("/admin/master-data/designations/{$job->id}/access")
            ->assertSessionHas('status', 'Nothing changed.');

        $this->assertSame(0, DB::table('audit_log')->where('action', AuditLog::PERMISSION_CHANGED)->count());
    }

    public function test_it_refuses_a_role_or_exception_it_should_not_offer(): void
    {
        $this->signInAsAdmin();

        $job = $this->designation();
        $url = "/admin/master-data/designations/{$job->id}/access";

        foreach (['mentor', 'employee', 'nonsense'] as $role) {
            $this->post($url, ['roles' => [$role]])->assertSessionHasErrors('roles.0');
        }

        $this->post($url, ['exceptions' => ['admin.settings.view' => 'allow']])->assertSessionHasErrors('exceptions');
        $this->post($url, ['exceptions' => ['clients.view' => 'maybe']])->assertSessionHasErrors('exceptions');

        $this->assertSame(0, DB::table('designation_permissions')->count());
    }

    public function test_only_a_designation_has_this_screen(): void
    {
        $this->signInAsAdmin();

        $department = MasterDataItem::create([
            'list' => MasterDataItem::DEPARTMENTS, 'name' => 'IT', 'code' => 'ITX', 'is_active' => true,
        ]);

        $this->get("/admin/master-data/designations/{$department->id}/access")->assertNotFound();
    }

    public function test_staff_cannot_reach_it_even_with_system_power(): void
    {
        $job = $this->designation();

        $this->signInAsStaff(['employee', 'system_admin']);

        $this->post("/admin/master-data/designations/{$job->id}/access", ['roles' => ['ceo']]);

        $this->assertSame(0, DB::table('designation_roles')->count());
    }

    public function test_the_lists_show_what_a_designation_carries_and_link_to_it(): void
    {
        $this->signInAsAdmin();

        $job = $this->designation();
        DB::table('designation_roles')->insert([
            'designation_id' => $job->id,
            'role_id' => Role::where('role_key', 'manager')->value('id'),
        ]);

        $this->get('/admin/master-data/designations')
            ->assertOk()
            ->assertSee('Carries Manager')
            ->assertSee("/admin/master-data/designations/{$job->id}/access");

        $this->get('/admin/master-data/departments')->assertOk()->assertDontSee('>Access<', false);
    }

    public function test_the_account_page_says_which_roles_come_from_the_designation(): void
    {
        $this->signInAsAdmin();

        $job = $this->designation();
        DB::table('designation_roles')->insert([
            'designation_id' => $job->id,
            'role_id' => Role::where('role_key', 'manager')->value('id'),
        ]);
        $holder = $this->person($job);

        $this->get('/admin/accounts/'.$holder->user_id)
            ->assertOk()
            ->assertSee('Also through their designation: Manager');
    }

    public function test_the_merge_page_warns_when_a_merge_changes_access(): void
    {
        $this->signInAsAdmin();

        $old = $this->designation('Old Title');
        $new = $this->designation('New Title');
        $this->person($old);

        DB::table('designation_roles')->insert([
            'designation_id' => $new->id,
            'role_id' => Role::where('role_key', 'manager')->value('id'),
        ]);

        $this->get('/admin/master-data/designations/merge?item='.$old->id)
            ->assertOk()
            ->assertSee('Merging into New Title changes access')
            ->assertSee('gains');
    }
}
