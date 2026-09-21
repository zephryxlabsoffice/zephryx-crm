<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Projects — creating, editing, and the end-of-day updates written against one.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MOST OF THIS FILE IS ABOUT ONE COLUMN
 *
 * `visibility` on an update decides whether a sentence somebody wrote at six in
 * the evening is read by the company it is about. The tests below hold the
 * three rules that make it safe: it defaults to internal, only somebody with
 * the permission can publish, and hiding it again is not a recall.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProjectWritesTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       THE GUARD IS ON THE ROUTE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_employee_may_see_projects_and_not_create_one(): void
    {
        $this->signInAsStaff(['employee']);

        $this->get('/projects')->assertOk();
        $this->get('/projects/create')->assertForbidden();
        $this->post('/projects', $this->validPayload())->assertForbidden();
    }

    public function test_a_manager_may_create_and_edit(): void
    {
        $project = $this->aProject();
        $this->signInAsStaff(['employee', 'manager']);

        $this->get('/projects/create')->assertOk();
        $this->get('/projects/'.$project->reference.'/edit')->assertOk();
    }

    public function test_publishing_is_a_sensitive_permission_of_its_own(): void
    {
        $this->assertTrue(
            (bool) Permission::where('permission_key', 'projects.publish')->value('is_sensitive')
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       CREATING AND EDITING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_creating_a_project_records_it_and_assigns_its_teams(): void
    {
        $team = Team::create([
            'reference' => 'TM-1500', 'name' => 'A Team', 'status' => 'active',
        ]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/projects', $this->validPayload(['teams' => [$team->id]]))->assertRedirect();

        $project = Project::where('name', 'A New Project')->first();

        $this->assertNotNull($project);
        $this->assertSame('PRJ-'.now()->year.'-001', $project->reference);
        $this->assertTrue($project->teams()->where('teams.id', $team->id)->exists());

        $entry = DB::table('audit_log')->where('action', AuditLog::PROJECT_CREATED)->first();
        $this->assertNotNull($entry);
    }

    public function test_a_project_must_have_a_client(): void
    {
        // Work in this application is work for somebody. A project with no
        // client is an invoice nobody can explain the origin of.
        $this->signInAsStaff(['employee', 'manager']);

        $payload = $this->validPayload();
        unset($payload['client_id']);

        $this->post('/projects', $payload)->assertSessionHasErrors('client_id');
    }

    public function test_a_deadline_before_the_start_is_refused(): void
    {
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/projects', $this->validPayload([
            'started_on' => Carbon::now()->toDateString(),
            'deadline' => Carbon::now()->subWeek()->toDateString(),
        ]))->assertSessionHasErrors('deadline');
    }

    public function test_unticking_a_team_takes_it_off_the_project(): void
    {
        $team = Team::create(['reference' => 'TM-1501', 'name' => 'Off Team', 'status' => 'active']);
        $project = $this->aProject();
        $project->teams()->sync([$team->id]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/projects/'.$project->reference, $this->validPayload([
            'name' => $project->name,
            'teams' => [],
        ]))->assertRedirect();

        $this->assertFalse($project->teams()->where('teams.id', $team->id)->exists());
        // The team itself is untouched — an assignment is not a membership.
        $this->assertNotNull($team->fresh());
    }

    public function test_a_status_change_gets_its_own_entry(): void
    {
        $project = $this->aProject();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/projects/'.$project->reference, $this->validPayload([
            'name' => $project->name,
            'status' => 'on_hold',
        ]));

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::PROJECT_STATUS_CHANGED)->count());
    }

    public function test_there_is_no_delete_route(): void
    {
        $project = $this->aProject();
        $this->signInAsStaff(['employee', 'manager']);

        $this->delete('/projects/'.$project->reference)->assertStatus(405);
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO MAY WRITE AN UPDATE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_somebody_not_on_the_project_cannot_write_against_it(): void
    {
        /*
         * Not a permission — reporting your own day is not an authority — so
         * it is an ownership check, and this is what it refuses.
         */
        $project = $this->aProject();

        $me = $this->signInAsStaff(['employee']);
        Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $this->get('/projects/'.$project->reference.'/eod')->assertForbidden();
        $this->post('/projects/'.$project->reference.'/eod', [
            'title' => 'Something', 'body' => 'Anything',
        ])->assertForbidden();
    }

    public function test_the_manager_of_a_project_may_write_against_it(): void
    {
        $me = $this->signInAsStaff(['employee']);
        $employee = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $project = $this->aProject(['manager_id' => $employee->id]);

        $this->get('/projects/'.$project->reference.'/eod')->assertOk();
    }

    public function test_somebody_in_an_assigned_team_may_write_against_it(): void
    {
        /*
         * "My projects" means the ones somebody is working on. The demo source
         * answered with the manager only, which quietly excluded everybody who
         * does the work.
         */
        $me = $this->signInAsStaff(['employee']);
        $employee = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $team = Team::create(['reference' => 'TM-1502', 'name' => 'My Team', 'status' => 'active']);
        $team->members()->syncWithoutDetaching([$employee->id]);

        $project = $this->aProject();
        $project->teams()->sync([$team->id]);

        $this->get('/projects/'.$project->reference.'/eod')->assertOk();
        $this->get('/projects/mine')->assertSee($project->name, false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       VISIBILITY — THE POINT OF THE MODULE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_update_is_internal_unless_somebody_says_otherwise(): void
    {
        $me = $this->signInAsStaff(['employee']);
        $employee = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);
        $project = $this->aProject(['manager_id' => $employee->id]);

        $this->post('/projects/'.$project->reference.'/eod', [
            'title' => 'Blocked on the invoice',
            'body' => 'Holding the migration until March is settled.',
        ])->assertRedirect();

        $update = ProjectUpdate::firstOrFail();

        $this->assertSame(ProjectUpdate::INTERNAL, $update->visibility);
        $this->assertNull($update->published_at);
    }

    public function test_the_database_default_is_internal_too(): void
    {
        /*
         * Not merely the form's default. A row inserted by a seeder, a script
         * or a future import must be internal unless it says otherwise — the
         * direction of that default is the whole safety argument.
         */
        $project = $this->aProject();
        $author = $this->anEmployee('EMP700', 'An Author');

        DB::table('project_updates')->insert([
            'reference' => 'UPD-TEST-001',
            'project_id' => $project->id,
            'author_id' => $author->id,
            'title' => 'Inserted directly',
            'body' => 'By something that never saw the form.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            ProjectUpdate::INTERNAL,
            DB::table('project_updates')->where('reference', 'UPD-TEST-001')->value('visibility')
        );
    }

    public function test_somebody_without_the_permission_cannot_publish_by_posting_the_field(): void
    {
        /*
         * The field is on the form for people who may publish. Somebody who may
         * not can still send it — so the request is not trusted: the update is
         * saved, internal, rather than refused. Losing somebody's written
         * account of their day over a tick box would be the worse outcome.
         */
        $me = $this->signInAsStaff(['employee']);
        $employee = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);
        $project = $this->aProject(['manager_id' => $employee->id]);

        $this->post('/projects/'.$project->reference.'/eod', [
            'title' => 'Trying to publish',
            'body' => 'With a field I should not have.',
            'visibility' => ProjectUpdate::CLIENT,
        ])->assertRedirect();

        $update = ProjectUpdate::firstOrFail();

        $this->assertSame(ProjectUpdate::INTERNAL, $update->visibility);
        $this->assertSame('Trying to publish', $update->title);
    }

    public function test_a_manager_may_publish_and_it_is_audited(): void
    {
        $me = $this->signInAsStaff(['employee', 'manager']);
        $employee = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);
        $project = $this->aProject(['manager_id' => $employee->id]);

        $this->post('/projects/'.$project->reference.'/eod', [
            'title' => 'Homepage signed off',
            'body' => 'Ready for your look.',
            'visibility' => ProjectUpdate::CLIENT,
        ])->assertRedirect();

        $update = ProjectUpdate::firstOrFail();

        $this->assertSame(ProjectUpdate::CLIENT, $update->visibility);
        $this->assertNotNull($update->published_at);

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::PROJECT_UPDATE_PUBLISHED)->count());
    }

    public function test_hiding_a_published_update_is_not_a_recall(): void
    {
        /*
         * `published_at` is set once and never cleared. If the client has read
         * it, they have read it, and no screen may imply that hiding it undid
         * anything.
         */
        $project = $this->aProject();
        $author = $this->anEmployee('EMP701', 'An Author');

        $update = ProjectUpdate::create([
            'reference' => 'UPD-TEST-002',
            'project_id' => $project->id,
            'author_id' => $author->id,
            'title' => 'Already out there',
            'body' => 'Read by the client last week.',
            'visibility' => ProjectUpdate::CLIENT,
            'published_at' => Carbon::now()->subWeek(),
        ]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/projects/'.$project->reference.'/updates/'.$update->reference.'/visibility', [
            'visibility' => ProjectUpdate::INTERNAL,
        ])->assertRedirect();

        $update->refresh();

        $this->assertSame(ProjectUpdate::INTERNAL, $update->visibility);
        $this->assertTrue($update->wasPublished(), 'published_at was cleared, which would deny it ever happened');
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::PROJECT_UPDATE_HIDDEN)->count());
    }

    public function test_an_internal_update_is_not_reachable_through_the_client_scope(): void
    {
        // §6, at the query layer. The client portal has no call that returns
        // both kinds and then filters.
        $project = $this->aProject();
        $author = $this->anEmployee('EMP702', 'An Author');

        ProjectUpdate::create([
            'reference' => 'UPD-TEST-003',
            'project_id' => $project->id,
            'author_id' => $author->id,
            'title' => 'Holding the migration',
            'body' => 'Until the March invoice is settled.',
            'visibility' => ProjectUpdate::INTERNAL,
        ]);

        $this->assertSame(0, ProjectUpdate::clientVisible()->count());
    }

    public function test_an_update_cannot_be_published_from_another_projects_page(): void
    {
        // The reference is looked up within the project in the URL, so an
        // update id from somewhere else is a 404 rather than a write.
        $mine = $this->aProject();
        $theirs = $this->aProject(['reference' => 'PRJ-OTHER-001', 'name' => 'Their Project']);
        $author = $this->anEmployee('EMP703', 'An Author');

        $update = ProjectUpdate::create([
            'reference' => 'UPD-TEST-004',
            'project_id' => $theirs->id,
            'author_id' => $author->id,
            'title' => 'Theirs',
            'body' => 'Not mine to publish.',
        ]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/projects/'.$mine->reference.'/updates/'.$update->reference.'/visibility', [
            'visibility' => ProjectUpdate::CLIENT,
        ])->assertNotFound();

        $this->assertSame(ProjectUpdate::INTERNAL, $update->fresh()->visibility);
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
            'name' => 'A New Project',
            'client_id' => $this->aClient()->id,
            'status' => 'planning',
            'priority' => 'medium',
            'deadline' => Carbon::now()->addMonth()->toDateString(),
        ];
    }

    protected function aClient(): Client
    {
        return Client::firstOrCreate(
            ['name' => 'A Client'],
            ['reference' => 'CLT900', 'status' => 'active'],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function aProject(array $attributes = []): Project
    {
        return Project::create($attributes + [
            'reference' => 'PRJ-TEST-001',
            'name' => 'Original Project',
            'client_id' => $this->aClient()->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'deadline' => Carbon::now()->addMonth(),
        ]);
    }

    protected function anEmployee(string $staffId, string $name): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
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
