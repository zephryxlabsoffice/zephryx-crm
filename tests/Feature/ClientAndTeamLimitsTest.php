<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\Dashboard\DashboardData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Who adds and edits clients, and who sees which clients and teams (owner, 2026-11-03).
 */
class ClientAndTeamLimitsTest extends TestCase
{
    protected function aClient(string $name, array $attributes = []): Client
    {
        return Client::create($attributes + [
            'reference' => 'CLT'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'status' => 'active',
        ]);
    }

    protected function employeeFor(User $user): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->subYear()->toDateString(),
        ]);
    }

    protected function aTeam(string $name, array $attributes = []): Team
    {
        return Team::create($attributes + [
            'reference' => 'TM-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'status' => 'active',
            'formed_on' => Carbon::now()->subYear(),
        ]);
    }

    #[TestWith(['client_relations', 200])]
    #[TestWith(['ceo', 200])]
    #[TestWith(['system_admin', 200])]
    #[TestWith(['hr', 403])]
    #[TestWith(['manager', 403])]
    #[TestWith(['team_lead', 403])]
    public function test_only_three_roles_may_add_and_edit_a_client(string $role, int $status): void
    {
        $client = $this->aClient('Anyone Ltd');

        $this->signInAsStaff(['employee', $role]);

        $this->get('/clients/create')->assertStatus($status);
        $this->get('/clients/'.$client->reference.'/edit')->assertStatus($status);
    }

    public function test_a_manager_sees_only_clients_linked_to_them(): void
    {
        $manager = $this->signInAsStaff(['employee', 'manager']);
        $employee = $this->employeeFor($manager);

        $named = $this->aClient('Named On Client', ['account_manager_id' => $manager->id]);
        $throughProject = $this->aClient('Through A Project');
        $unrelated = $this->aClient('Somebody Elses');

        Project::create([
            'reference' => 'PRJ-T-1', 'name' => 'Their Project', 'client_id' => $throughProject->id,
            'manager_id' => $employee->id, 'progress' => 0, 'status' => 'planning', 'priority' => 'medium',
            'deadline' => Carbon::today()->addMonth(),
        ]);

        $this->get('/clients')
            ->assertOk()
            ->assertSee('Named On Client')
            ->assertSee('Through A Project')
            ->assertDontSee('Somebody Elses');

        $this->get('/clients/'.$named->reference)->assertOk();
        $this->get('/clients/'.$throughProject->reference)->assertOk();
        $this->get('/clients/'.$unrelated->reference)->assertForbidden();

        $this->assertSame('2', (string) DashboardData::kpi('clients', $employee)['value']);
    }

    #[TestWith(['client_relations'])]
    #[TestWith(['hr'])]
    #[TestWith(['support'])]
    public function test_other_roles_still_see_every_client(string $role): void
    {
        $client = $this->aClient('Anyone Ltd');

        $this->signInAsStaff(['employee', $role]);

        $this->get('/clients')->assertOk()->assertSee('Anyone Ltd');
        $this->get('/clients/'.$client->reference)->assertOk();
    }

    #[TestWith(['manager'])]
    #[TestWith(['team_lead'])]
    public function test_managers_and_team_leads_cannot_see_invoices(string $role): void
    {
        $this->signInAsStaff(['employee', $role]);

        $this->get('/invoices')->assertForbidden();
    }

    public function test_a_team_lead_sees_only_the_teams_they_lead_or_belong_to(): void
    {
        $lead = $this->signInAsStaff(['employee', 'team_lead']);
        $employee = $this->employeeFor($lead);

        $led = $this->aTeam('Team They Lead', ['lead_id' => $employee->id]);
        $joined = $this->aTeam('Team They Joined');
        $joined->members()->attach($employee->id, ['joined_at' => now()]);
        $unrelated = $this->aTeam('Team Elsewhere');

        $this->get('/teams')
            ->assertOk()
            ->assertSee('Team They Lead')
            ->assertSee('Team They Joined')
            ->assertDontSee('Team Elsewhere');

        $this->get('/teams/'.$led->reference)->assertOk();
        $this->get('/teams/'.$joined->reference)->assertOk();
        $this->get('/teams/'.$unrelated->reference)->assertForbidden();
    }

    public function test_a_plain_employee_sees_only_their_own_teams_too(): void
    {
        $employee = $this->employeeFor($this->signInAsStaff(['employee']));

        $joined = $this->aTeam('Team They Joined');
        $joined->members()->attach($employee->id, ['joined_at' => now()]);
        $unrelated = $this->aTeam('Team Elsewhere');

        $this->get('/teams')->assertOk()->assertSee('Team They Joined')->assertDontSee('Team Elsewhere');
        $this->get('/teams/'.$unrelated->reference)->assertForbidden();
    }

    public function test_a_manager_still_sees_every_team(): void
    {
        $team = $this->aTeam('Team Elsewhere');

        $this->signInAsStaff(['employee', 'manager']);

        $this->get('/teams')->assertOk()->assertSee('Team Elsewhere');
        $this->get('/teams/'.$team->reference)->assertOk();
    }

    public function test_the_migration_applies_these_rules_to_a_live_database(): void
    {
        DB::table('permissions')->whereIn('permission_key', ['clients.view.all', 'teams.view.all'])->delete();

        foreach (['hr', 'manager'] as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => DB::table('roles')->where('role_key', $role)->value('id'),
                'permission_id' => DB::table('permissions')->where('permission_key', 'clients.edit')->value('id'),
            ]);
        }

        DB::table('role_permissions')
            ->where('role_id', DB::table('roles')->where('role_key', 'system_admin')->value('id'))
            ->where('permission_id', DB::table('permissions')->where('permission_key', 'clients.edit')->value('id'))
            ->delete();

        (require database_path('migrations/2026_11_03_000001_limit_who_sees_and_edits_clients_and_teams.php'))->up();

        $holds = fn (string $role, string $key) => DB::table('role_permissions')
            ->where('role_id', DB::table('roles')->where('role_key', $role)->value('id'))
            ->where('permission_id', DB::table('permissions')->where('permission_key', $key)->value('id'))
            ->exists();

        $this->assertFalse($holds('hr', 'clients.edit'));
        $this->assertFalse($holds('manager', 'clients.edit'));
        $this->assertTrue($holds('system_admin', 'clients.edit'));
        $this->assertTrue($holds('client_relations', 'clients.edit'));
        $this->assertTrue($holds('ceo', 'clients.edit'));

        $this->assertFalse($holds('manager', 'clients.view.all'));
        $this->assertTrue($holds('manager', 'clients.view'));
        $this->assertTrue($holds('hr', 'clients.view.all'));
        $this->assertTrue($holds('system_admin', 'clients.view.all'));

        $this->assertFalse($holds('team_lead', 'teams.view.all'));
        $this->assertTrue($holds('team_lead', 'teams.view'));
        $this->assertFalse($holds('employee', 'teams.view.all'));
        $this->assertTrue($holds('manager', 'teams.view.all'));
        $this->assertTrue($holds('support', 'teams.view.all'));
    }
}
