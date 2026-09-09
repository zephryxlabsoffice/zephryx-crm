<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Teams — creating, editing, and changing who is in one.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE FILE IS MOSTLY ABOUT THE SECOND CHECK
 *
 * The permission says what somebody may do; §2.6 says who they may do it to. A
 * Team Lead holds `teams.members` for their own team and no other, and that
 * distinction cannot live on a route — so most of what follows is a lead being
 * allowed on one team and refused on the one next to it.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TeamWritesTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       THE GUARD IS ON THE ROUTE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_employee_may_see_teams_and_not_create_one(): void
    {
        $this->signInAsStaff(['employee']);

        $this->get('/teams')->assertOk();
        $this->get('/teams/create')->assertForbidden();
        $this->post('/teams', $this->validPayload())->assertForbidden();
    }

    public function test_the_personal_face_needs_no_permission_at_all(): void
    {
        // §12.1: it is the viewer's own membership, reachable by anybody signed
        // in — the same reason their own payslip is.
        $this->signInAsStaff(['employee']);

        $this->get('/teams/mine')->assertOk();
    }

    public function test_a_manager_may_create_and_edit(): void
    {
        $team = $this->aTeam();
        $this->signInAsStaff(['employee', 'manager']);

        $this->get('/teams/create')->assertOk();
        $this->get('/teams/'.$team->reference.'/edit')->assertOk();
    }

    public function test_a_team_lead_may_not_rename_a_team(): void
    {
        // Membership is the routine act; renaming a team and naming its lead
        // are a wider authority the Team Lead role does not carry.
        $this->aTeam();
        $this->signInAsStaff(['employee', 'team_lead']);

        $this->get('/teams/create')->assertForbidden();
        $this->get('/teams/TM-1500/edit')->assertForbidden();
    }

    /* ══════════════════════════════════════════════════════════════════════
       CREATING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_creating_a_team_gives_it_a_reference_and_records_it(): void
    {
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams', $this->validPayload())->assertRedirect();

        $team = Team::where('name', 'Platform Team')->first();

        $this->assertNotNull($team);
        $this->assertSame('TM-1001', $team->reference);

        $entry = DB::table('audit_log')->where('action', AuditLog::TEAM_CREATED)->first();

        $this->assertNotNull($entry);
        $this->assertSame('TM-1001', $entry->entity_id);
    }

    public function test_the_lead_is_a_member_of_the_team_they_lead(): void
    {
        /*
         * Not enforced by the schema — leading a team you are not in is a real
         * if unusual arrangement — but it is what naming a lead means, and
         * having to add them separately is the step everybody forgets.
         */
        $lead = $this->anEmployee('EMP600', 'A Lead');
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams', $this->validPayload(['lead_id' => $lead->id]));

        $team = Team::where('name', 'Platform Team')->firstOrFail();

        $this->assertTrue($team->members()->where('employees.id', $lead->id)->exists());
    }

    public function test_a_duplicate_team_name_is_refused(): void
    {
        $existing = $this->aTeam();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams', $this->validPayload(['name' => $existing->name]))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Team::count());
    }

    public function test_the_reference_is_derived_from_the_highest_and_never_reissued(): void
    {
        $this->seedDemoWorkforce();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams', $this->validPayload());

        // The demo teams run to TM-1010.
        $this->assertNotNull(Team::where('reference', 'TM-1011')->first());
    }

    /* ══════════════════════════════════════════════════════════════════════
       EDITING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_editing_records_what_it_was_before(): void
    {
        $team = $this->aTeam();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams/'.$team->reference, $this->validPayload(['name' => 'Renamed Team']))
            ->assertRedirect();

        $entry = DB::table('audit_log')->where('action', AuditLog::TEAM_UPDATED)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Original Team', (string) $entry->before_json);
        $this->assertStringContainsString('Renamed Team', (string) $entry->after_json);
    }

    public function test_a_status_change_gets_its_own_entry(): void
    {
        /*
         * "When did this team stop being used, and who decided" is what the
         * activity rail is asked. An update entry buries the answer in a
         * sentence about four other fields.
         */
        $team = $this->aTeam();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams/'.$team->reference, $this->validPayload([
            'name' => $team->name,
            'status' => 'inactive',
        ]));

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TEAM_STATUS_CHANGED)->count());
    }

    public function test_saving_without_changing_the_status_writes_no_status_entry(): void
    {
        $team = $this->aTeam();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams/'.$team->reference, $this->validPayload(['name' => $team->name]));

        $this->assertSame(0, DB::table('audit_log')->where('action', AuditLog::TEAM_STATUS_CHANGED)->count());
    }

    public function test_there_is_no_delete_route(): void
    {
        // Tasks and projects will point at teams.
        $team = $this->aTeam();
        $this->signInAsStaff(['employee', 'manager']);

        $this->delete('/teams/'.$team->reference)->assertStatus(405);
    }

    /* ══════════════════════════════════════════════════════════════════════
       MEMBERSHIP, AND THE §2.6 RULE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_team_lead_may_manage_their_own_team(): void
    {
        $me = $this->signInAsStaff(['employee', 'team_lead']);
        $mine = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $team = $this->aTeam(['lead_id' => $mine->id]);
        $other = $this->anEmployee('EMP601', 'Another Person');

        $this->post('/teams/'.$team->reference.'/members', ['employee_id' => $other->id])
            ->assertRedirect();

        $this->assertTrue($team->members()->where('employees.id', $other->id)->exists());
    }

    public function test_a_team_lead_may_not_manage_a_team_they_do_not_lead(): void
    {
        /*
         * The whole of §2.6 in one test. The permission opened the route; the
         * ownership check refused the team. Without it, a lead could reassign
         * anybody's team by typing its reference into the URL.
         */
        $me = $this->signInAsStaff(['employee', 'team_lead']);
        Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $someoneElse = $this->anEmployee('EMP602', 'Their Lead');
        $theirTeam = $this->aTeam(['lead_id' => $someoneElse->id]);
        $victim = $this->anEmployee('EMP603', 'A Member');

        $this->post('/teams/'.$theirTeam->reference.'/members', ['employee_id' => $victim->id])
            ->assertForbidden();

        $this->assertFalse($theirTeam->members()->where('employees.id', $victim->id)->exists());
    }

    public function test_a_manager_may_manage_any_team(): void
    {
        $team = $this->aTeam();
        $member = $this->anEmployee('EMP604', 'A Member');

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams/'.$team->reference.'/members', ['employee_id' => $member->id])
            ->assertRedirect();

        $this->assertTrue($team->members()->where('employees.id', $member->id)->exists());
    }

    public function test_adding_the_same_person_twice_produces_one_membership(): void
    {
        // A button somebody clicks twice on a slow connection must not be a 500
        // from the unique key, and must not be two rows either.
        $team = $this->aTeam();
        $member = $this->anEmployee('EMP605', 'A Member');

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams/'.$team->reference.'/members', ['employee_id' => $member->id]);
        $this->post('/teams/'.$team->reference.'/members', ['employee_id' => $member->id]);

        $this->assertSame(1, $team->members()->where('employees.id', $member->id)->count());
    }

    public function test_somebody_whose_record_is_closed_cannot_be_added(): void
    {
        // A closed record on a roster is somebody who cannot sign in, listed as
        // though they can.
        $team = $this->aTeam();
        $closed = $this->anEmployee('EMP606', 'Gone Person', status: 'inactive');

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams/'.$team->reference.'/members', ['employee_id' => $closed->id])
            ->assertSessionHasErrors('employee_id');

        $this->assertFalse($team->members()->where('employees.id', $closed->id)->exists());
    }

    public function test_the_lead_cannot_be_removed_while_they_lead_it(): void
    {
        // A team showing a lead who is not in it is a state nobody arrives at
        // on purpose.
        $lead = $this->anEmployee('EMP607', 'The Lead');
        $team = $this->aTeam(['lead_id' => $lead->id]);
        $team->members()->syncWithoutDetaching([$lead->id]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams/'.$team->reference.'/members/remove', ['employee_id' => $lead->id])
            ->assertSessionHasErrors('employee_id');

        $this->assertTrue($team->members()->where('employees.id', $lead->id)->exists());
    }

    public function test_removing_somebody_is_audited(): void
    {
        $team = $this->aTeam();
        $member = $this->anEmployee('EMP608', 'A Member');
        $team->members()->syncWithoutDetaching([$member->id]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/teams/'.$team->reference.'/members/remove', ['employee_id' => $member->id])
            ->assertRedirect();

        $this->assertFalse($team->members()->where('employees.id', $member->id)->exists());

        $entry = DB::table('audit_log')
            ->where('action', AuditLog::TEAM_MEMBERS_CHANGED)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('A Member', (string) $entry->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Platform Team',
            'purpose' => 'Shared infrastructure',
            'status' => 'active',
            'formed_on' => Carbon::now()->subMonth()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function aTeam(array $attributes = []): Team
    {
        return Team::create($attributes + [
            'reference' => 'TM-1500',
            'name' => 'Original Team',
            'purpose' => 'Something',
            'status' => 'active',
            'formed_on' => Carbon::now()->subYear(),
        ]);
    }

    protected function anEmployee(string $staffId, string $name, string $status = 'active'): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => $status,
        ]);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    /**
     * Give the signed-in account extra permissions without inventing a role.
     */
    protected function grant(string ...$permissions): void
    {
        $role = Role::firstOrCreate(
            ['role_key' => 'test_grant'],
            ['role_name' => 'Test grant', 'is_active' => true],
        );

        $role->permissions()->syncWithoutDetaching(
            Permission::whereIn('permission_key', $permissions)->pluck('id')
        );

        auth()->user()->roles()->syncWithoutDetaching([$role->id]);
        app(Rbac::class)->forget(auth()->user());
    }
}
