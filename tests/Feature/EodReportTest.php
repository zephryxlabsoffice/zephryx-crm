<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EodItem;
use App\Models\EodReport;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The end of day report: filing it, what it does to tasks, and who may read it.
 */
class EodReportTest extends TestCase
{
    /* ── filing ───────────────────────────────────────────────────────────── */

    public function test_ticking_an_assigned_task_completes_it_with_no_other_step(): void
    {
        $me = $this->person(['employee'], 'EMP900');
        $task = $this->taskFor($me);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed']],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame($task->name, EodItem::sole()->title);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::EOD_FILED)->count());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TASK_COMPLETED)->count());
    }

    public function test_an_unticked_task_is_left_alone_even_with_a_status_sent(): void
    {
        $me = $this->person(['employee'], 'EMP901');
        $task = $this->taskFor($me);
        $other = $this->taskFor($me);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'assigned' => [
                $task->id => ['status' => 'completed'],
                $other->id => ['checked' => 1, 'status' => 'in_progress'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('pending', $task->fresh()->status);
        $this->assertSame('in_progress', $other->fresh()->status);
    }

    public function test_unassigned_work_is_reported_without_becoming_a_task(): void
    {
        $me = $this->person(['employee'], 'EMP902');
        $project = $this->project();

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'extra' => [
                ['project' => (string) $project->id, 'title' => 'Fixed the printer', 'status' => 'completed', 'comment' => 'Paper jam'],
                ['project' => 'none', 'title' => 'Helped a colleague', 'status' => 'in_progress'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Task::count());

        $items = EodItem::orderBy('id')->get();
        $this->assertSame($project->id, $items[0]->project_id);
        $this->assertSame('Paper jam', $items[0]->comment);
        $this->assertNull($items[1]->project_id);
    }

    public function test_unassigned_work_needs_a_project_a_title_and_a_status(): void
    {
        $me = $this->person(['employee'], 'EMP903');

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'extra' => [['project' => '', 'title' => '', 'status' => 'nonsense']],
        ])->assertSessionHasErrors(['extra.0.project', 'extra.0.title', 'extra.0.status']);
    }

    public function test_an_empty_report_is_refused(): void
    {
        $me = $this->person(['employee'], 'EMP904');

        $this->actingAs($me->user);
        $this->post('/tasks/eod', ['report_date' => Carbon::today()->toDateString()])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, EodReport::count());
    }

    public function test_a_task_assigned_to_somebody_else_cannot_be_reported(): void
    {
        $me = $this->person(['employee'], 'EMP905');
        $task = $this->taskFor($this->person(['employee'], 'EMP906'));

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed']],
        ])->assertSessionHasErrors('assigned');

        $this->assertSame('pending', $task->fresh()->status);
    }

    /* ── dates ────────────────────────────────────────────────────────────── */

    public function test_a_report_can_be_filed_for_an_earlier_day_and_says_so(): void
    {
        $me = $this->person(['employee'], 'EMP907');
        $task = $this->taskFor($me);
        $day = Carbon::today()->subDays(3);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => $day->toDateString(),
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed']],
        ])->assertSessionHasNoErrors();

        $report = EodReport::sole();

        $this->assertTrue($report->wasFiledLate());
        $this->assertSame($day->toDateString(), $task->fresh()->completed_at->toDateString());
        $this->get('/tasks/eod/'.$report->id)->assertOk()->assertSee('filed late');
    }

    public function test_a_future_day_is_refused(): void
    {
        $me = $this->person(['employee'], 'EMP908');

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::tomorrow()->toDateString(),
            'extra' => [['project' => 'none', 'title' => 'Not yet', 'status' => 'completed']],
        ])->assertSessionHasErrors('report_date');
    }

    public function test_filing_a_day_twice_updates_the_one_report(): void
    {
        $me = $this->person(['employee'], 'EMP909');
        $date = Carbon::today()->toDateString();

        $this->actingAs($me->user);
        $this->post('/tasks/eod', ['report_date' => $date, 'extra' => [['project' => 'none', 'title' => 'First', 'status' => 'completed']]]);
        $this->post('/tasks/eod', ['report_date' => $date, 'extra' => [['project' => 'none', 'title' => 'Second', 'status' => 'completed']]]);

        $this->assertSame(1, EodReport::count());
        $this->assertSame(['Second'], EodItem::pluck('title')->all());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::EOD_UPDATED)->count());
    }

    public function test_opening_a_filed_day_shows_what_was_said(): void
    {
        $me = $this->person(['employee'], 'EMP910');
        $task = $this->taskFor($me);
        $date = Carbon::today()->subDay()->toDateString();

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => $date,
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed', 'comment' => 'All done here']],
            'extra' => [['project' => 'none', 'title' => 'Side job', 'status' => 'review']],
        ]);

        // The task is completed now, and still has to appear ticked on its own day.
        $this->get('/tasks/eod?date='.$date)
            ->assertOk()
            ->assertSee($task->name)
            ->assertSee('value="All done here"', false)
            ->assertSee('value="Side job"', false)
            ->assertSee('Update report');
    }

    public function test_an_older_report_does_not_undo_a_newer_one(): void
    {
        $me = $this->person(['employee'], 'EMP911');
        $task = $this->taskFor($me);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed']],
        ]);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->subDays(2)->toDateString(),
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'in_progress']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_a_task_starting_later_is_not_offered_until_its_day(): void
    {
        $me = $this->person(['employee'], 'EMP912');
        $future = $this->taskFor($me, ['name' => 'Next week job', 'starts_on' => Carbon::today()->addWeek()]);
        $this->assertNotNull($future);

        $this->actingAs($me->user);
        $this->get('/tasks/eod')->assertOk()->assertDontSee('Next week job');
        $this->get('/tasks/mine')->assertOk()->assertSee('Next week job');

        Carbon::setTestNow(Carbon::today()->addWeek());
        $this->get('/tasks/eod')->assertOk()->assertSee('Next week job');
        Carbon::setTestNow();
    }

    /* ── assigning for the future ─────────────────────────────────────────── */

    public function test_a_team_lead_may_assign_a_task_to_start_in_the_future(): void
    {
        $lead = $this->person(['employee', 'team_lead'], 'EMP913');
        $worker = $this->person(['employee'], 'EMP914');
        $startsOn = Carbon::today()->addDays(5)->toDateString();

        $this->actingAs($lead->user);
        $this->post('/tasks', [
            'name' => 'Future work',
            'status' => 'pending',
            'priority' => 'medium',
            'starts_on' => $startsOn,
            'due_on' => Carbon::today()->addDays(9)->toDateString(),
            'assignee_ids' => [$worker->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($startsOn, Task::sole()->starts_on->toDateString());
    }

    public function test_a_task_cannot_be_due_before_it_starts(): void
    {
        $lead = $this->person(['employee', 'team_lead'], 'EMP915');

        $this->actingAs($lead->user);
        $this->post('/tasks', [
            'name' => 'Backwards',
            'status' => 'pending',
            'priority' => 'medium',
            'starts_on' => Carbon::today()->addDays(9)->toDateString(),
            'due_on' => Carbon::today()->addDays(5)->toDateString(),
        ])->assertSessionHasErrors('due_on');
    }

    /* ── who it goes to, and who may read it ──────────────────────────────── */

    public function test_it_goes_to_the_reporting_manager_project_manager_and_team_leads_once_each(): void
    {
        $reportingManager = $this->person(['employee', 'manager'], 'EMP916');
        $projectManager = $this->person(['employee', 'manager'], 'EMP917');
        $teamLead = $this->person(['employee', 'team_lead'], 'EMP918');
        $unrelated = $this->person(['employee', 'manager'], 'EMP919');

        $me = $this->person(['employee'], 'EMP920');
        $me->update(['reports_to' => $reportingManager->id]);

        $team = Team::create(['reference' => 'TEAM-EOD', 'name' => 'Eod Team', 'lead_id' => $teamLead->id, 'status' => 'active', 'formed_on' => now()]);
        $team->members()->attach($me->id);

        $project = $this->project($projectManager);
        $task = $this->taskFor($me, ['project_id' => $project->id]);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed']],
        ])->assertSessionHasNoErrors();

        $report = EodReport::sole();

        $this->assertEqualsCanonicalizing(
            [$reportingManager->id, $projectManager->id, $teamLead->id],
            $report->recipients->pluck('id')->all(),
        );

        foreach ([$reportingManager, $projectManager, $teamLead] as $person) {
            $this->assertSame(1, Notification::where('user_id', $person->user_id)->where('kind', 'eod')->count());
        }

        $this->assertSame(0, Notification::where('user_id', $unrelated->user_id)->count());
    }

    public function test_the_same_person_in_two_roles_is_told_once(): void
    {
        $boss = $this->person(['employee', 'manager'], 'EMP921');

        $me = $this->person(['employee'], 'EMP922');
        $me->update(['reports_to' => $boss->id]);

        $team = Team::create(['reference' => 'TEAM-TWO', 'name' => 'Two Hats', 'lead_id' => $boss->id, 'status' => 'active', 'formed_on' => now()]);
        $team->members()->attach($me->id);

        $task = $this->taskFor($me, ['project_id' => $this->project($boss)->id]);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed']],
        ]);

        $this->assertSame(1, Notification::where('user_id', $boss->user_id)->count());
    }

    public function test_a_recipient_can_read_it_and_an_outsider_cannot_tell_it_exists(): void
    {
        $boss = $this->person(['employee', 'manager'], 'EMP923');
        $outsider = $this->person(['employee', 'manager'], 'EMP924');

        $me = $this->person(['employee'], 'EMP925');
        $me->update(['reports_to' => $boss->id]);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'extra' => [['project' => 'none', 'title' => 'Private line', 'status' => 'completed']],
        ]);

        $report = EodReport::sole();

        $this->actingAs($boss->user);
        $this->get('/tasks/eod/'.$report->id)->assertOk()->assertSee('Private line');
        $this->get('/tasks/eod/team')->assertOk()->assertSee($me->user->name);

        $this->actingAs($outsider->user);
        $this->get('/tasks/eod/'.$report->id)->assertNotFound();
        $this->get('/tasks/eod/team')->assertOk()->assertDontSee($me->user->name);
    }

    public function test_the_ceo_can_read_everybodys(): void
    {
        $me = $this->person(['employee'], 'EMP926');

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'extra' => [['project' => 'none', 'title' => 'Line', 'status' => 'completed']],
        ]);

        $this->actingAs($this->person(['ceo'], 'EMP927')->user);
        $this->get('/tasks/eod/'.EodReport::sole()->id)->assertOk();
    }

    public function test_an_account_with_no_employee_record_cannot_file(): void
    {
        $this->actingAs(User::factory()->create(['account_type' => 'staff', 'staff_kind' => null, 'status' => 'active']));

        $this->get('/tasks/eod')->assertForbidden();
        $this->post('/tasks/eod', ['report_date' => Carbon::today()->toDateString()])->assertForbidden();
    }

    /* ── the section ──────────────────────────────────────────────────────── */

    public function test_an_assignee_reports_through_the_eod_rather_than_a_button_on_the_task(): void
    {
        $me = $this->person(['employee'], 'EMP928');
        $task = $this->taskFor($me);

        $this->actingAs($me->user);

        $this->get('/tasks/'.$task->reference)
            ->assertOk()
            ->assertDontSee('Mark Completed')
            ->assertSee(route('tasks.eod'), false);
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        $me = $this->person(['employee'], 'EMP929');
        $this->taskFor($me);

        $this->actingAs($me->user);

        $html = $this->get('/tasks/eod')->assertOk()->getContent();

        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('style="', $html);
    }

    /* ── helpers ──────────────────────────────────────────────────────────── */

    /**
     * @param  list<string>  $roles
     */
    protected function person(array $roles, string $staffId): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));
        app(Rbac::class)->forget($user);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function taskFor(Employee $employee, array $overrides = []): Task
    {
        $task = Task::create($overrides + [
            'reference' => 'TSK-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Job '.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'pending',
            'priority' => 'medium',
            'due_on' => Carbon::today()->addWeek(),
        ]);

        $task->assignees()->attach($employee->id);

        return $task;
    }

    protected function project(?Employee $manager = null): Project
    {
        return Project::create([
            'reference' => 'PRJ-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Project '.fake()->unique()->numberBetween(1000, 9999),
            'client_id' => Client::create([
                'reference' => 'CLI-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Client '.fake()->unique()->numberBetween(1000, 9999),
            ])->id,
            'manager_id' => $manager?->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'started_on' => Carbon::today()->subMonth(),
            'deadline' => Carbon::today()->addMonth(),
        ]);
    }
}
