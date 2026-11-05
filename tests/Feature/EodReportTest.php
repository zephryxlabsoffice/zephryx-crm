<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EodItem;
use App\Models\EodReport;
use App\Models\EodUrgentRequest;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Admin\CompanySettings;
use App\Support\Audit\AuditLog;
use App\Support\EodPolicy;
use App\Support\EodReminders;
use App\Support\EodUrgent;
use App\Support\LeavePresenter;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The end of day report: filing it, what it does to tasks, and who may read it.
 */
class EodReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Running, unless a test below says otherwise. The shipped date passes by itself.
        config(['tasks.eod_starts_on' => null]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ── filing an older day ──────────────────────────────────────────────── */

    public function test_anybody_with_days_can_file_an_older_day_after_checking_out(): void
    {
        Carbon::setTestNow('2026-11-04 10:00:00');

        $yesterday = '2026-11-03';

        foreach ([['employee'], ['employee', 'team_lead'], ['employee', 'manager']] as $index => $roles) {
            $person = $this->person($roles, 'EMP95'.$index);
            $task = $this->taskFor($person);

            DB::table('attendance_records')->insert([
                'employee_id' => $person->id,
                'date' => $yesterday,
                'check_in' => '09:30:00',
                'check_out' => '17:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs($person->user);

            $this->get('/tasks/eod?date='.$yesterday)->assertOk()->assertSee($task->name);

            $this->post('/tasks/eod', [
                'report_date' => $yesterday,
                'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed']],
            ])->assertSessionHasNoErrors();

            $this->assertSame('completed', $task->fresh()->status, implode(', ', $roles));
            $this->assertTrue(EodReport::where('employee_id', $person->id)->sole()->wasFiledLate());
        }
    }

    public function test_a_task_finished_after_that_day_is_still_offered_for_it(): void
    {
        Carbon::setTestNow('2026-11-04 10:00:00');

        $me = $this->person(['employee'], 'EMP954');
        $finishedLater = $this->taskFor($me);
        $finishedBefore = $this->taskFor($me, [
            'status' => 'completed',
            'completed_at' => '2026-11-01 16:00:00',
        ]);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => '2026-11-03',
            'assigned' => [$finishedLater->id => ['checked' => 1, 'status' => 'completed']],
        ]);

        $this->get('/tasks/eod?date=2026-11-02')
            ->assertOk()
            ->assertSee($finishedLater->name)
            ->assertDontSee($finishedBefore->name);
    }

    public function test_an_older_day_cannot_reopen_a_task_changed_since_but_can_finish_one(): void
    {
        Carbon::setTestNow('2026-11-04 10:00:00');

        $me = $this->person(['employee'], 'EMP955');
        $closedByALead = $this->taskFor($me, ['status' => 'completed', 'completed_at' => '2026-11-04 09:00:00']);
        $stillOpen = $this->taskFor($me);

        $this->actingAs($me->user);
        $this->post('/tasks/eod', [
            'report_date' => '2026-11-02',
            'assigned' => [
                $closedByALead->id => ['checked' => 1, 'status' => 'in_progress'],
                $stillOpen->id => ['checked' => 1, 'status' => 'completed'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('completed', $closedByALead->fresh()->status);
        $this->assertSame('completed', $stillOpen->fresh()->status);
    }

    /* ── urgent ───────────────────────────────────────────────────────────── */

    public function test_a_manager_marks_a_missing_report_urgent_and_the_person_is_told_on_every_page(): void
    {
        Http::fake();
        Carbon::setTestNow('2026-11-04 10:00:00');

        $boss = $this->person(['employee', 'manager'], 'EMP956');
        $late = $this->person(['employee'], 'EMP957');
        $late->update(['reports_to' => $boss->id]);

        $this->actingAs($boss->user);
        $this->get('/tasks/eod/team?date=2026-11-03')->assertSee('Mark urgent');
        $this->post('/tasks/eod/urgent', ['employee' => $late->id, 'date' => '2026-11-03'])
            ->assertRedirect('/tasks/eod/team?date=2026-11-03')
            ->assertSessionHas('status_tone', 'success');

        $this->get('/tasks/eod/team?date=2026-11-03')->assertSee('Urgent, marked by')->assertDontSee('Mark urgent');

        $bell = Notification::where('user_id', $late->user_id)->where('kind', EodUrgent::KIND)->sole();
        $this->assertStringContainsString('date=2026-11-03', $bell->link());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::EOD_URGENT)->count());

        $this->actingAs($late->user);
        $this->get('/tasks/mine')->assertSee('Urgent: your End of Day for Tuesday 3 November')->assertSee('date=2026-11-03', false);
    }

    public function test_marking_twice_tells_the_person_once(): void
    {
        Http::fake();
        Carbon::setTestNow('2026-11-04 10:00:00');

        $boss = $this->person(['employee', 'manager'], 'EMP958');
        $late = $this->person(['employee'], 'EMP959');
        $late->update(['reports_to' => $boss->id]);

        $this->actingAs($boss->user);
        $this->post('/tasks/eod/urgent', ['employee' => $late->id, 'date' => '2026-11-03']);
        $this->post('/tasks/eod/urgent', ['employee' => $late->id, 'date' => '2026-11-03'])
            ->assertSessionHas('status_tone', 'info');

        $this->assertSame(1, Notification::where('user_id', $late->user_id)->where('kind', EodUrgent::KIND)->count());
    }

    public function test_only_somebody_waiting_on_that_person_can_mark_it(): void
    {
        Http::fake();
        Carbon::setTestNow('2026-11-04 10:00:00');

        $otherBoss = $this->person(['employee', 'manager'], 'EMP960');
        $colleague = $this->person(['employee'], 'EMP961');
        $late = $this->person(['employee'], 'EMP962');

        foreach ([$otherBoss, $colleague] as $outsider) {
            $this->actingAs($outsider->user);
            $this->post('/tasks/eod/urgent', ['employee' => $late->id, 'date' => '2026-11-03'])
                ->assertSessionHas('status_tone', 'info');
        }

        $this->assertSame(0, EodUrgentRequest::count());
        $this->assertSame(0, Notification::where('user_id', $late->user_id)->count());
    }

    public function test_a_team_lead_can_mark_a_member_and_the_ceo_can_mark_anybody(): void
    {
        Http::fake();
        Carbon::setTestNow('2026-11-04 10:00:00');

        $lead = $this->person(['employee', 'team_lead'], 'EMP963');
        $member = $this->person(['employee'], 'EMP964');
        Team::create(['reference' => 'TEAM-UR', 'name' => 'Urgent', 'lead_id' => $lead->id, 'status' => 'active', 'formed_on' => now()])
            ->members()->attach($member->id);
        $ceo = $this->person(['employee', 'ceo'], 'EMP965');
        $anybody = $this->person(['employee'], 'EMP966');

        $this->actingAs($lead->user);
        $this->post('/tasks/eod/urgent', ['employee' => $member->id, 'date' => '2026-11-03'])->assertSessionHas('status_tone', 'success');

        $this->actingAs($ceo->user);
        $this->post('/tasks/eod/urgent', ['employee' => $anybody->id, 'date' => '2026-11-03'])->assertSessionHas('status_tone', 'success');

        $this->assertSame(2, EodUrgentRequest::count());
    }

    public function test_somebody_who_has_filed_or_is_on_leave_cannot_be_marked(): void
    {
        Http::fake();
        Carbon::setTestNow('2026-11-04 10:00:00');

        $boss = $this->person(['employee', 'manager'], 'EMP967');
        $filed = $this->person(['employee'], 'EMP968');
        $filed->update(['reports_to' => $boss->id]);
        $onLeave = $this->person(['employee'], 'EMP969');
        $onLeave->update(['reports_to' => $boss->id]);
        $this->approvedLeave($onLeave, '2026-11-03', '2026-11-03');

        $this->actingAs($filed->user);
        $this->post('/tasks/eod', [
            'report_date' => '2026-11-03',
            'extra' => [['project' => 'none', 'title' => 'Line', 'status' => 'completed']],
        ]);

        $this->actingAs($boss->user);
        $this->post('/tasks/eod/urgent', ['employee' => $filed->id, 'date' => '2026-11-03']);
        $this->post('/tasks/eod/urgent', ['employee' => $onLeave->id, 'date' => '2026-11-03']);

        $this->assertSame(0, EodUrgentRequest::count());
    }

    public function test_filing_clears_the_notice_and_tells_whoever_marked_it_once(): void
    {
        Http::fake();
        Carbon::setTestNow('2026-11-04 10:00:00');

        $boss = $this->person(['employee', 'manager'], 'EMP970');
        $ceo = $this->person(['employee', 'ceo'], 'EMP971');
        $late = $this->person(['employee'], 'EMP972');
        $late->update(['reports_to' => $boss->id]);

        $this->actingAs($ceo->user);
        $this->post('/tasks/eod/urgent', ['employee' => $late->id, 'date' => '2026-11-03']);

        $this->actingAs($late->user);
        $this->get('/tasks/eod?date=2026-11-03')->assertSee('needs this report');
        $this->post('/tasks/eod', [
            'report_date' => '2026-11-03',
            'extra' => [['project' => 'none', 'title' => 'Line', 'status' => 'completed']],
        ])->assertSessionHasNoErrors();

        $this->get('/tasks/mine')->assertDontSee('File it now');

        $report = EodReport::sole();
        $this->assertTrue($report->recipients->contains('id', $ceo->id));

        $told = Notification::where('user_id', $ceo->user_id)->where('kind', 'eod')->sole();
        $this->assertStringContainsString('marked urgent', $told->title);
        $this->assertSame(1, Notification::where('user_id', $boss->user_id)->where('kind', 'eod')->count());

        $this->actingAs($ceo->user);
        $this->get('/tasks/eod/'.$report->id)->assertOk()->assertSee('marked urgent by');
    }

    public function test_an_urgent_report_cannot_be_marked_before_the_start_date(): void
    {
        config(['tasks.eod_starts_on' => Carbon::today()->addMonth()->toDateString()]);

        $boss = $this->person(['employee', 'manager'], 'EMP973');
        $late = $this->person(['employee'], 'EMP974');
        $late->update(['reports_to' => $boss->id]);

        $this->actingAs($boss->user);
        $this->post('/tasks/eod/urgent', ['employee' => $late->id, 'date' => Carbon::today()->toDateString()])->assertForbidden();
    }

    public function test_pages_still_open_before_the_update_has_been_applied(): void
    {
        Schema::drop('eod_urgent_requests');

        $me = $this->person(['employee'], 'EMP975');

        $this->actingAs($me->user);
        $this->get('/tasks/mine')->assertOk();
        $this->get('/tasks/eod')->assertOk();
    }

    /* ── before it starts ─────────────────────────────────────────────────── */

    public function test_before_its_start_date_the_form_is_closed_and_tasks_finish_as_before(): void
    {
        config(['tasks.eod_starts_on' => Carbon::today()->addMonth()->toDateString()]);

        $me = $this->person(['employee'], 'EMP930');
        $task = $this->taskFor($me);

        $this->actingAs($me->user);

        $this->get('/tasks/eod')->assertOk()->assertSee('End of Day starts on')->assertDontSee('data-eod-form', false);

        $this->post('/tasks/eod', [
            'report_date' => Carbon::today()->toDateString(),
            'assigned' => [$task->id => ['checked' => 1, 'status' => 'completed']],
        ])->assertForbidden();

        $this->assertSame('pending', $task->fresh()->status);

        $this->get('/tasks/'.$task->reference)->assertOk()->assertSee('Mark Completed')->assertDontSee('Report in End of Day');
        $this->get('/tasks/mine')->assertOk()->assertDontSee(route('tasks.eod'), false);
    }

    public function test_it_opens_by_itself_on_its_start_date(): void
    {
        config(['tasks.eod_starts_on' => '2026-12-01']);
        Carbon::setTestNow('2026-12-01 09:00:00');

        $me = $this->person(['employee'], 'EMP931');

        $this->actingAs($me->user);

        $this->get('/tasks/eod')->assertOk()->assertSee('data-eod-form', false);
    }

    public function test_a_day_before_the_start_date_cannot_be_filed(): void
    {
        config(['tasks.eod_starts_on' => '2026-12-01']);
        Carbon::setTestNow('2026-12-03 09:00:00');

        $me = $this->person(['employee'], 'EMP932');

        $this->actingAs($me->user);

        $this->post('/tasks/eod', [
            'report_date' => '2026-11-30',
            'extra' => [['project' => 'none', 'title' => 'Too early', 'status' => 'completed']],
        ])->assertSessionHasErrors('report_date');
    }

    public function test_the_start_date_is_an_admin_setting(): void
    {
        $this->signInAsAdmin();

        $this->get('/admin/settings')->assertOk()->assertSee('End of Day starts on');

        $this->post('/admin/settings', ['key' => 'tasks.eod_starts_on', 'value' => '2027-01-04', 'reason' => 'Moved'])
            ->assertRedirect();

        CompanySettings::apply();

        $this->assertSame('2027-01-04', EodPolicy::startsOn()->toDateString());
    }

    /* ── not filed yet ────────────────────────────────────────────────────── */

    public function test_a_manager_sees_who_has_not_filed_among_those_they_are_waiting_on(): void
    {
        Carbon::setTestNow('2026-11-04 17:00:00');

        $boss = $this->person(['employee', 'manager'], 'EMP933');

        $filed = $this->person(['employee'], 'EMP934');
        $filed->update(['reports_to' => $boss->id]);

        $missing = $this->person(['employee'], 'EMP935');
        $missing->update(['reports_to' => $boss->id]);

        $inMyTeam = $this->person(['employee'], 'EMP936');
        Team::create(['reference' => 'TEAM-NF', 'name' => 'Waiting', 'lead_id' => $boss->id, 'status' => 'active', 'formed_on' => now()])
            ->members()->attach($inMyTeam->id);

        $onLeave = $this->person(['employee'], 'EMP937');
        $onLeave->update(['reports_to' => $boss->id]);
        $this->approvedLeave($onLeave, '2026-11-03', '2026-11-05');

        $joinedLater = $this->person(['employee'], 'EMP938');
        $joinedLater->update(['reports_to' => $boss->id, 'joined_on' => '2026-11-20']);

        $somebodyElses = $this->person(['employee'], 'EMP939');

        $this->actingAs($filed->user);
        $this->post('/tasks/eod', [
            'report_date' => '2026-11-04',
            'extra' => [['project' => 'none', 'title' => 'Line', 'status' => 'completed']],
        ]);

        $this->actingAs($boss->user);

        $names = $this->get('/tasks/eod/team?date=2026-11-04')->assertOk()->viewData('notFiled')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$missing->id, $inMyTeam->id], $names);
        $this->assertNotContains($somebodyElses->id, $names);
    }

    public function test_the_ceo_is_waiting_on_everybody(): void
    {
        Carbon::setTestNow('2026-11-04 17:00:00');

        $anyone = $this->person(['employee'], 'EMP941');

        $this->actingAs($this->person(['ceo'], 'EMP942')->user);

        $this->assertContains(
            $anyone->id,
            $this->get('/tasks/eod/team?date=2026-11-04')->viewData('notFiled')->pluck('id')->all(),
        );
    }

    public function test_nobody_owes_a_report_for_a_week_off(): void
    {
        Carbon::setTestNow('2026-11-09 17:00:00');
        config(['attendance.week_off' => [0]]);

        $boss = $this->person(['employee', 'manager'], 'EMP943');
        $this->person(['employee'], 'EMP944')->update(['reports_to' => $boss->id]);

        $this->actingAs($boss->user);

        // 8 November 2026 is a Sunday.
        $this->get('/tasks/eod/team?date=2026-11-08')
            ->assertOk()
            ->assertSee('not a working day');
    }

    /* ── the reminder ─────────────────────────────────────────────────────── */

    public function test_the_reminder_reaches_only_those_who_have_not_filed_and_only_once(): void
    {
        Carbon::setTestNow('2026-11-04 18:00:00');
        Http::fake(['*' => Http::response('', 201)]);

        $filed = $this->person(['employee'], 'EMP945');
        $missing = $this->person(['employee'], 'EMP946');
        $onLeave = $this->person(['employee'], 'EMP947');
        $this->approvedLeave($onLeave, '2026-11-04', '2026-11-04');

        $this->actingAs($filed->user);
        $this->post('/tasks/eod', [
            'report_date' => '2026-11-04',
            'extra' => [['project' => 'none', 'title' => 'Line', 'status' => 'completed']],
        ]);

        $this->actingAs($missing->user);
        $this->post('/push/subscribe', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/missing']);

        $this->artisan('eod:remind')->assertSuccessful();
        $this->artisan('eod:remind')->assertSuccessful();

        $reminded = Notification::where('kind', EodReminders::KIND)->pluck('user_id')->all();

        $this->assertSame([$missing->user_id], $reminded);
        Http::assertSentCount(1);

        $this->getJson('/push/latest')->assertJsonPath('title', 'Your End of Day is not filed yet');
    }

    public function test_the_reminder_stays_quiet_before_the_start_date_and_on_a_week_off(): void
    {
        $this->person(['employee'], 'EMP948');

        config(['tasks.eod_starts_on' => '2026-12-01']);
        Carbon::setTestNow('2026-11-04 18:00:00');
        $this->artisan('eod:remind')->assertSuccessful();

        config(['tasks.eod_starts_on' => null, 'attendance.week_off' => [0]]);
        Carbon::setTestNow('2026-11-08 18:00:00');
        $this->artisan('eod:remind')->assertSuccessful();

        $this->assertSame(0, Notification::where('kind', EodReminders::KIND)->count());
    }

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

    protected function approvedLeave(Employee $employee, string $from, string $to): void
    {
        DB::table('leave_requests')->insert([
            'reference' => 'LV-'.fake()->unique()->numberBetween(1000, 9999),
            'employee_id' => $employee->id,
            'type' => 'casual',
            'from_date' => $from,
            'to_date' => $to,
            'days' => 1,
            'reason' => 'Away',
            'status' => LeavePresenter::APPROVED,
            'applied_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
