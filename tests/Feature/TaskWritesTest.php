<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tasks — creating, editing, assigning and completing.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THREE DIFFERENT ANSWERS TO "MAY THEY?", AND THIS FILE HOLDS ALL THREE
 *
 * Creating and editing are permissions. Assigning is a permission plus an
 * ownership rule — a Team Lead may assign within a team they lead and no other.
 * Completing is ownership alone: saying you have finished your own work is not
 * an authority, and a permission would either be held by everybody or would
 * stop people closing the work they did.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TaskWritesTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       THE GUARD IS ON THE ROUTE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_employee_may_see_tasks_and_not_create_one(): void
    {
        $this->signInAsStaff(['employee']);

        $this->get('/tasks')->assertOk();
        $this->get('/tasks/create')->assertForbidden();
        $this->post('/tasks', $this->validPayload())->assertForbidden();
    }

    public function test_a_manager_may_create_and_edit(): void
    {
        $task = $this->aTask();
        $this->signInAsStaff(['employee', 'manager']);

        $this->get('/tasks/create')->assertOk();
        $this->get('/tasks/'.$task->reference.'/edit')->assertOk();
    }

    public function test_a_team_lead_may_create_but_not_edit_the_plan(): void
    {
        /*
         * Creating a task is a Team Lead's act too (review round decision,
         * 2026-09-21: "created by Manager and Team Lead"). Assigning and
         * creating are theirs; the project, the deadline and the priority on
         * an EXISTING task are the plan, and editing that is still not.
         */
        $this->aTask();
        $this->signInAsStaff(['employee', 'team_lead']);

        $this->get('/tasks/create')->assertOk();
        $this->get('/tasks/TSK-900/edit')->assertForbidden();
    }

    /* ══════════════════════════════════════════════════════════════════════
       CREATING AND EDITING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_creating_a_task_records_it(): void
    {
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks', $this->validPayload())->assertRedirect();

        $task = Task::where('name', 'A New Task')->first();

        $this->assertNotNull($task);
        $this->assertSame('TSK-001', $task->reference);

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TASK_CREATED)->count());
    }

    public function test_a_task_may_be_created_with_a_team_and_nobody_on_it(): void
    {
        /*
         * The Team Lead's queue is exactly this state. Requiring an assignee
         * would delete the case the /tasks/team page exists for.
         */
        $team = $this->aTeam();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks', $this->validPayload(['team_id' => $team->id]))->assertRedirect();

        $task = Task::where('name', 'A New Task')->firstOrFail();

        $this->assertTrue($task->assignees->isEmpty());
        $this->get('/tasks/team')->assertSee($task->reference, false);
    }

    public function test_assigning_at_creation_is_recorded_separately(): void
    {
        // "Who put this person on it" is what the timeline is read for, and an
        // update entry buries the answer in a payload.
        $employee = $this->anEmployee('EMP800', 'A Worker');
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks', $this->validPayload(['assignee_ids' => [$employee->id]]));

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TASK_ASSIGNED)->count());
    }

    public function test_editing_records_what_it_was_before(): void
    {
        $task = $this->aTask();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks/'.$task->reference, $this->validPayload(['name' => 'Renamed Task']))
            ->assertRedirect();

        $entry = DB::table('audit_log')->where('action', AuditLog::TASK_UPDATED)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Original Task', (string) $entry->before_json);
        $this->assertStringContainsString('Renamed Task', (string) $entry->after_json);
    }

    public function test_there_is_no_delete_route(): void
    {
        $task = $this->aTask();
        $this->signInAsStaff(['employee', 'manager']);

        $this->delete('/tasks/'.$task->reference)->assertStatus(405);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ASSIGNING — THE §2.6 RULE AGAIN
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_team_lead_may_assign_within_their_own_team(): void
    {
        $me = $this->signInAsStaff(['employee', 'team_lead']);
        $mine = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $team = $this->aTeam(['lead_id' => $mine->id]);
        $task = $this->aTask(['team_id' => $team->id]);
        $worker = $this->anEmployee('EMP801', 'A Worker');

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_ids' => [$worker->id]])
            ->assertRedirect();

        $this->assertSame([$worker->id], $task->fresh()->assignees->pluck('id')->all());
    }

    public function test_a_team_lead_may_not_assign_on_another_teams_task(): void
    {
        // The permission opened the route; the ownership check refuses the
        // queue. Without it, a lead could take over anybody's board.
        $me = $this->signInAsStaff(['employee', 'team_lead']);
        Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $theirLead = $this->anEmployee('EMP802', 'Their Lead');
        $theirTeam = $this->aTeam(['lead_id' => $theirLead->id]);
        $task = $this->aTask(['team_id' => $theirTeam->id]);
        $worker = $this->anEmployee('EMP803', 'A Worker');

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_ids' => [$worker->id]])
            ->assertForbidden();

        $this->assertTrue($task->fresh()->assignees->isEmpty());
    }

    public function test_taking_somebody_off_puts_it_back_in_the_queue(): void
    {
        $worker = $this->anEmployee('EMP804', 'A Worker');
        $team = $this->aTeam();
        $task = $this->aTask(['team_id' => $team->id, 'assignees' => [$worker->id]]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_ids' => []])->assertRedirect();

        $this->assertTrue($task->fresh()->assignees->isEmpty());
        $this->get('/tasks/team')->assertSee($task->reference, false);
    }

    public function test_somebody_whose_record_is_closed_cannot_be_given_work(): void
    {
        $closed = $this->anEmployee('EMP805', 'Gone Person', status: 'inactive');
        $task = $this->aTask();

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_ids' => [$closed->id]])
            ->assertSessionHasErrors('assignee_ids');

        $this->assertTrue($task->fresh()->assignees->isEmpty());
    }

    /* ══════════════════════════════════════════════════════════════════════
       COMPLETING — OWNERSHIP, NOT AUTHORITY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_assignee_may_complete_their_own_task_without_any_permission(): void
    {
        $me = $this->signInAsStaff(['employee']);
        $employee = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $task = $this->aTask(['assignees' => [$employee->id]]);

        $this->post('/tasks/'.$task->reference.'/complete', ['status' => 'completed'])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TASK_COMPLETED)->count());
    }

    public function test_somebody_unconnected_to_the_task_may_not_complete_it(): void
    {
        $me = $this->signInAsStaff(['employee']);
        Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $task = $this->aTask(['assignees' => [$this->anEmployee('EMP806', 'Somebody Else')->id]]);

        $this->post('/tasks/'.$task->reference.'/complete', ['status' => 'completed'])
            ->assertForbidden();

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_the_lead_of_the_team_holding_it_may_complete_it(): void
    {
        // Their queue, and a task nobody picked up still has to be closable.
        $me = $this->signInAsStaff(['employee', 'team_lead']);
        $mine = Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $team = $this->aTeam(['lead_id' => $mine->id]);
        $task = $this->aTask(['team_id' => $team->id]);

        $this->post('/tasks/'.$task->reference.'/complete', ['status' => 'completed'])
            ->assertRedirect();

        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_reopening_does_not_erase_that_it_was_delivered(): void
    {
        /*
         * A task delivered and then reopened is a different thing from one
         * never finished, and a report asking how long work takes has to be
         * able to tell them apart. The status moves; the fact stays.
         */
        $task = $this->aTask([
            'status' => 'completed',
            'completed_at' => Carbon::now()->subWeek(),
        ]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks/'.$task->reference.'/complete', ['status' => 'in_progress'])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame('in_progress', $task->status);
        $this->assertNotNull($task->completed_at, 'completed_at was cleared, denying it was ever delivered');
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TASK_REOPENED)->count());
    }

    public function test_completing_a_task_that_is_already_complete_writes_nothing(): void
    {
        $task = $this->aTask(['status' => 'completed', 'completed_at' => Carbon::now()->subDay()]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks/'.$task->reference.'/complete', ['status' => 'completed'])->assertRedirect();

        $this->assertSame(0, DB::table('audit_log')->where('action', AuditLog::TASK_COMPLETED)->count());
    }

    public function test_there_is_no_completion_permission_to_hold(): void
    {
        // Finishing your own work is not an authority, so no key exists for it.
        $this->assertNull(Permission::where('permission_key', 'tasks.complete')->first());
    }

    /* ══════════════════════════════════════════════════════════════════════
       SEVERAL ASSIGNEES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_task_may_carry_more_than_one_assignee(): void
    {
        // "A task can go to a team and flow to its members" (review round
        // decision) — two people pairing on the same work is the ordinary
        // case this pivot exists for.
        $task = $this->aTask();
        $first = $this->anEmployee('EMP807', 'First Worker');
        $second = $this->anEmployee('EMP808', 'Second Worker');

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks/'.$task->reference.'/assign', [
            'assignee_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        $this->assertSame(
            [$first->id, $second->id],
            $task->fresh()->assignees->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_adding_a_second_assignee_does_not_renotify_the_first(): void
    {
        // A sync that adds one person to a task somebody else is already on
        // must not tell the person already there about work they have had.
        $first = $this->anEmployee('EMP809', 'Already On It');
        $task = $this->aTask(['assignees' => [$first->id]]);
        $second = $this->anEmployee('EMP810', 'Newly Added');

        $this->signInAsStaff(['employee', 'manager']);

        DB::table('audit_log')->delete();

        $this->post('/tasks/'.$task->reference.'/assign', [
            'assignee_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        $this->assertSame(
            0,
            DB::table('notifications')->where('user_id', $first->user_id)->count(),
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       COMMENTS AND ATTACHMENTS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_comment_is_recorded_and_audited(): void
    {
        $task = $this->aTask();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/tasks/'.$task->reference.'/comment', ['body' => 'Blocked on the API key.'])
            ->assertRedirect();

        $comment = $task->comments()->first();

        $this->assertNotNull($comment);
        $this->assertSame('Blocked on the API key.', $comment->body);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TASK_COMMENTED)->count());
    }

    public function test_a_file_can_be_attached(): void
    {
        $task = $this->aTask();
        $this->signInAsStaff(['employee', 'manager']);
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/tasks/'.$task->reference.'/attachments', [
            'document' => UploadedFile::fake()->create('spec.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(1, $task->attachments()->count());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TASK_ATTACHMENT_ADDED)->count());
    }

    public function test_an_attachment_from_another_task_cannot_be_reached(): void
    {
        $task = $this->aTask();
        $other = $this->aTask(['reference' => 'TSK-901']);
        $this->signInAsStaff(['employee', 'manager']);
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/tasks/'.$other->reference.'/attachments', [
            'document' => UploadedFile::fake()->create('spec.pdf', 20, 'application/pdf'),
        ]);

        $attachment = $other->attachments()->firstOrFail();

        $this->get('/tasks/'.$task->reference.'/attachments/'.$attachment->id.'/download')
            ->assertNotFound();
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
            'name' => 'A New Task',
            'status' => 'pending',
            'priority' => 'medium',
            'due_on' => Carbon::now()->addWeek()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes  an 'assignees' key (a list
     *                                            of employee ids) is synced onto the pivot rather than passed to
     *                                            Task::create — assignee_id is not a column any more.
     */
    protected function aTask(array $attributes = []): Task
    {
        $assignees = $attributes['assignees'] ?? null;
        unset($attributes['assignees']);

        $task = Task::create($attributes + [
            'reference' => 'TSK-900',
            'name' => 'Original Task',
            'status' => 'in_progress',
            'priority' => 'medium',
            'due_on' => Carbon::now()->addWeek(),
        ]);

        if ($assignees !== null) {
            $task->assignees()->sync($assignees);
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function aTeam(array $attributes = []): Team
    {
        return Team::create($attributes + [
            'reference' => 'TM-1600',
            'name' => 'A Team',
            'status' => 'active',
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
