<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\LeavePresenter;
use App\Support\NotificationDirectory;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Notifications — the bell, and the six events that fill it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THIS FILE IS REALLY GUARDING
 *
 * Three properties, and none of them is about the page rendering:
 *
 * 1. NOBODY READS ANYBODY ELSE'S. Both the page and the bell, and the write
 *    that marks things read.
 * 2. NOTHING TELLS YOU WHAT YOU JUST DID. One rule in `Notifier::send`, relied
 *    on by six call sites, so it is tested through the call sites.
 * 3. AN INTERNAL NOTE STAYS INTERNAL. A notification is the one surface that
 *    could carry a ticket's private thread out to somebody the visibility rules
 *    were hiding it from.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class NotificationsTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       SCOPING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_page_shows_only_the_signed_in_person_s_own(): void
    {
        $mine = $this->signInWith(['employee'], 'EMP950');
        $theirs = $this->makeEmployee('EMP951');

        Notification::create([
            'user_id' => $mine->user_id,
            'kind' => 'task',
            'title' => 'Addressed to me',
            'body' => 'Mine.',
        ]);

        Notification::create([
            'user_id' => $theirs->user_id,
            'kind' => 'task',
            'title' => 'Addressed to somebody else',
            'body' => 'Not mine.',
        ]);

        $body = $this->pageBody('/notifications');

        $this->assertStringContainsString('Addressed to me', $body);
        $this->assertStringNotContainsString('Addressed to somebody else', $body);
    }

    public function test_there_is_no_way_to_ask_for_everybody_s_notifications(): void
    {
        /*
         * Decided 2026-08-28, carried over from DemoNotifications, and asserted
         * rather than merely written down: forgetting to scope should be a
         * syntax error. Every method takes the reader as its first argument and
         * there is no `all()`, so the read that leaks one queue into another
         * cannot be written by accident.
         */
        $this->assertFalse(method_exists(NotificationDirectory::class, 'all'));

        foreach (get_class_methods(NotificationDirectory::class) as $method) {
            $first = (new ReflectionMethod(NotificationDirectory::class, $method))->getParameters()[0] ?? null;

            $this->assertNotNull($first, $method.'() takes no reader');
            $this->assertSame(User::class, (string) $first->getType(), $method.'() does not take a reader first');
        }
    }

    public function test_the_relation_on_an_account_is_ours_and_not_laravel_s(): void
    {
        /*
         * `User` carried Laravel's `Notifiable` trait from the scaffold, unused
         * — and it claimed the `notifications` table for `DatabaseNotification`,
         * whose shape is nothing like the one §8 specifies. That collision
         * returns wrong rows rather than throwing, so it is pinned here: the
         * relation has to come back as OUR model.
         */
        $me = $this->signInWith(['employee'], 'EMP958');

        Notification::create([
            'user_id' => $me->user_id, 'kind' => 'task', 'title' => 'Mine', 'body' => 'x',
        ]);

        $user = User::findOrFail($me->user_id);

        $this->assertFalse(method_exists($user, 'notify'));
        $this->assertInstanceOf(Notification::class, $user->notifications->firstOrFail());
    }

    public function test_the_bell_on_every_page_is_scoped_the_same_way(): void
    {
        /*
         * The bell is drawn by a view composer on the layout, not by the
         * notifications controller — so scoping it is a second decision made in
         * a second place, and one that leaks onto every page in the
         * application rather than onto one.
         */
        $this->signInWith(['employee'], 'EMP952');
        $theirs = $this->makeEmployee('EMP953');

        Notification::create([
            'user_id' => $theirs->user_id,
            'kind' => 'ticket',
            'title' => 'Somebody else was assigned a ticket',
            'body' => 'Not mine.',
        ]);

        $this->assertStringNotContainsString(
            'Somebody else was assigned a ticket',
            $this->get('/dashboard')->getContent(),
        );
    }

    public function test_marking_read_touches_nobody_else_s_rows(): void
    {
        $mine = $this->signInWith(['employee'], 'EMP954');
        $theirs = $this->makeEmployee('EMP955');

        $ours = Notification::create([
            'user_id' => $mine->user_id, 'kind' => 'task', 'title' => 'Mine', 'body' => 'x',
        ]);

        $other = Notification::create([
            'user_id' => $theirs->user_id, 'kind' => 'task', 'title' => 'Theirs', 'body' => 'x',
        ]);

        $this->post('/notifications/read')->assertRedirect('/notifications');

        $this->assertNotNull($ours->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at, "somebody else's queue was cleared");
    }

    public function test_marking_read_does_not_move_a_timestamp_that_is_already_set(): void
    {
        /*
         * `read_at` says when somebody SAW a thing. Pressing the button a
         * second time, about something else, must not rewrite that — a log of
         * when people read things, where every entry says "just now", records
         * nothing.
         */
        $mine = $this->signInWith(['employee'], 'EMP956');

        $seenLastWeek = Notification::create([
            'user_id' => $mine->user_id,
            'kind' => 'leave',
            'title' => 'Read a while ago',
            'body' => 'x',
            'read_at' => Carbon::now()->subWeek(),
        ]);

        $was = $seenLastWeek->read_at;

        $this->post('/notifications/read')->assertRedirect();

        $this->assertTrue($was->equalTo($seenLastWeek->fresh()->read_at));
    }

    public function test_reading_your_own_queue_is_behind_no_permission(): void
    {
        /*
         * Deliberate, and the one module with no §5 gate on it. A permission
         * here would be a control over whether somebody may read something
         * addressed to them, and one nobody could ever safely revoke — it would
         * silently stop leave decisions reaching people.
         */
        $employee = $this->signInWith([], 'EMP957');

        Notification::create([
            'user_id' => $employee->user_id, 'kind' => 'leave', 'title' => 'Yours', 'body' => 'x',
        ]);

        $this->get('/notifications')->assertOk();
        $this->post('/notifications/read')->assertRedirect();

        $this->assertNotNull(Notification::firstOrFail()->read_at);
    }

    /* ══════════════════════════════════════════════════════════════════════
       NOTHING TELLS YOU WHAT YOU JUST DID
       ══════════════════════════════════════════════════════════════════════ */

    public function test_assigning_a_task_notifies_the_assignee(): void
    {
        $lead = $this->signInWith(['employee', 'manager'], 'EMP960');
        $worker = $this->makeEmployee('EMP961');

        $task = $this->makeTask();

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_id' => $worker->id])
            ->assertRedirect();

        $notification = Notification::where('user_id', $worker->user_id)->firstOrFail();

        $this->assertSame('task', $notification->kind);
        $this->assertStringContainsString('assigned you a task', $notification->title);
        $this->assertStringContainsString($task->name, $notification->body);
        $this->assertSame(route('tasks.show', ['task' => $task->reference]), $notification->link());

        // And the person who did it hears nothing about their own act.
        $this->assertSame(0, Notification::where('user_id', $lead->user_id)->count());
    }

    public function test_assigning_a_task_to_yourself_notifies_nobody(): void
    {
        /*
         * The most common thing anybody does with the assign form, and the one
         * that turns a useful bell into a permanently-unread one full of things
         * the reader already knows.
         */
        $me = $this->signInWith(['employee', 'manager'], 'EMP962');

        $task = $this->makeTask();

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_id' => $me->id])
            ->assertRedirect();

        $this->assertSame(0, Notification::count());
    }

    public function test_taking_somebody_off_a_task_notifies_nobody(): void
    {
        $this->signInWith(['employee', 'manager'], 'EMP963');
        $worker = $this->makeEmployee('EMP964');

        $task = $this->makeTask(['assignee_id' => $worker->id]);

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_id' => null])
            ->assertRedirect();

        // Audited, because the plan changed. Not notified, because there is no
        // reader and nothing for anybody to do.
        $this->assertSame(0, Notification::count());
        $this->assertGreaterThan(0, DB::table('audit_log')->where('action', 'task.assigned')->count());
    }

    public function test_a_leave_decision_reaches_the_person_who_asked(): void
    {
        $approver = $this->signInWith(['employee', 'hr'], 'EMP965');
        $asker = $this->makeEmployee('EMP966');

        $request = LeaveRequest::create([
            'reference' => 'LV-2026-900',
            'employee_id' => $asker->id,
            'type' => 'casual',
            'from_date' => Carbon::today()->addWeek()->toDateString(),
            'to_date' => Carbon::today()->addWeek()->toDateString(),
            'days' => 1,
            'reason' => 'A day off, thank you.',
            'status' => LeavePresenter::PENDING,
            'applied_at' => Carbon::now(),
        ]);

        $this->post('/leave/'.$request->reference.'/approve', ['note' => 'Fine.'])->assertRedirect();

        $notification = Notification::where('user_id', $asker->user_id)->firstOrFail();

        // The decision is in the TITLE. A bell reading "your leave request was
        // updated" makes people open it to learn nothing.
        $this->assertSame('Your leave was approved', $notification->title);
        $this->assertSame(0, Notification::where('user_id', $approver->user_id)->count());
    }

    public function test_a_rejection_carries_the_reason(): void
    {
        $this->signInWith(['employee', 'hr'], 'EMP967');
        $asker = $this->makeEmployee('EMP968');

        $request = LeaveRequest::create([
            'reference' => 'LV-2026-901',
            'employee_id' => $asker->id,
            'type' => 'casual',
            'from_date' => Carbon::today()->addWeek()->toDateString(),
            'to_date' => Carbon::today()->addWeek()->toDateString(),
            'days' => 1,
            'reason' => 'A day off, thank you.',
            'status' => LeavePresenter::PENDING,
            'applied_at' => Carbon::now(),
        ]);

        $this->post('/leave/'.$request->reference.'/reject', ['note' => 'The release is that week.'])
            ->assertRedirect();

        $this->assertStringContainsString(
            'The release is that week.',
            Notification::where('user_id', $asker->user_id)->firstOrFail()->body,
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE TWO SWITCHES, AND THE TWO KINDS THAT HAVE NONE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_switching_task_notifications_off_actually_stops_them(): void
    {
        $this->signInWith(['employee', 'manager'], 'EMP940');
        $worker = $this->makeEmployee('EMP941');

        EmployeeProfile::create(['employee_id' => $worker->id, 'notify_tasks' => false]);

        $task = $this->makeTask();

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_id' => $worker->id])
            ->assertRedirect();

        $this->assertSame(0, Notification::where('user_id', $worker->user_id)->count());
    }

    public function test_a_leave_decision_arrives_whatever_the_switches_say(): void
    {
        /*
         * There is no switch for it, and this asserts that turning the other
         * two off does not quietly take it with them — a decision somebody is
         * waiting for must not be lost to a preference about task assignments.
         */
        $this->signInWith(['employee', 'hr'], 'EMP942');
        $asker = $this->makeEmployee('EMP943');

        EmployeeProfile::create([
            'employee_id' => $asker->id,
            'notify_tasks' => false,
            'notify_tickets' => false,
        ]);

        $request = LeaveRequest::create([
            'reference' => 'LV-2026-902',
            'employee_id' => $asker->id,
            'type' => 'casual',
            'from_date' => Carbon::today()->addWeek()->toDateString(),
            'to_date' => Carbon::today()->addWeek()->toDateString(),
            'days' => 1,
            'reason' => 'A day off, thank you.',
            'status' => LeavePresenter::PENDING,
            'applied_at' => Carbon::now(),
        ]);

        $this->post('/leave/'.$request->reference.'/approve', ['note' => 'Fine.'])->assertRedirect();

        $this->assertSame(1, Notification::where('user_id', $asker->user_id)->count());
    }

    public function test_a_reader_with_no_profile_row_gets_everything(): void
    {
        /*
         * Never opened My Profile. A missing row must not read as "switched
         * off" — that failure is silent and looks exactly like notifications
         * not working.
         */
        $this->signInWith(['employee', 'manager'], 'EMP944');
        $worker = $this->makeEmployee('EMP945');

        $this->assertNull($worker->profile);

        $task = $this->makeTask();

        $this->post('/tasks/'.$task->reference.'/assign', ['assignee_id' => $worker->id])
            ->assertRedirect();

        $this->assertSame(1, Notification::where('user_id', $worker->user_id)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       AN INTERNAL NOTE STAYS INTERNAL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_ticket_notification_never_carries_the_comment_text(): void
    {
        /*
         * The one surface that could carry a private thread out to somebody the
         * visibility column was hiding it from. Asserted on the PUBLIC reply as
         * well as the internal note: the rule is that the text never leaves the
         * thread, not that internal text does — a rule with an exception is one
         * somebody eventually gets the wrong way round.
         */
        $author = $this->signInWith(['employee', 'ceo'], 'EMP970');
        $onIt = $this->makeEmployee('EMP971');

        $ticket = Ticket::create([
            'reference' => 'TKT-2026-900',
            'type' => 'internal',
            'subject' => 'The build is red',
            'description' => 'Since this morning.',
            'assignee_id' => $onIt->id,
            'status' => 'open',
        ]);

        foreach ([TicketComment::INTERNAL, TicketComment::PUBLIC] as $visibility) {
            $secret = 'The credentials are in '.$visibility.' storage';

            $this->post('/tickets/'.$ticket->reference.'/comment', [
                'body' => $secret,
                'visibility' => $visibility,
            ])->assertRedirect();

            $notification = Notification::where('user_id', $onIt->user_id)->latest('id')->firstOrFail();

            $this->assertStringNotContainsString($secret, $notification->body);
            $this->assertStringContainsString($ticket->reference, $notification->body);
        }

        $this->assertSame(0, Notification::where('user_id', $author->user_id)->count());
    }

    public function test_re_saving_triage_does_not_notify_the_same_person_again(): void
    {
        /*
         * Triage is one write covering four decisions. Correcting the category
         * a week later must not tell the person on the ticket that it has been
         * assigned to them — they know; they have had it since the first save.
         */
        $this->signInWith(['employee', 'ceo'], 'EMP972');
        $onIt = $this->makeEmployee('EMP973');

        $ticket = Ticket::create([
            'reference' => 'TKT-2026-901',
            'type' => 'internal',
            'subject' => 'A laptop, please',
            'description' => 'The old one has stopped.',
            'status' => 'unassigned',
        ]);

        $triage = ['assignee_id' => $onIt->id, 'priority' => 'medium', 'category' => 'hardware'];

        $this->post('/tickets/'.$ticket->reference.'/triage', $triage)->assertRedirect();
        $this->assertSame(1, Notification::where('user_id', $onIt->user_id)->count());

        $this->post('/tickets/'.$ticket->reference.'/triage', $triage + ['category' => 'equipment'])
            ->assertRedirect();

        $this->assertSame(1, Notification::where('user_id', $onIt->user_id)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       MEETINGS, WHERE THE PROVIDER IS ALLOWED TO FAIL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_attendees_are_told_even_though_the_calendar_invite_failed(): void
    {
        /*
         * The reason this notification exists at all. Every provider method
         * throws today (see GoogleMeetProvider) and will throw again on the day
         * Google is down — so the row written here is the only thing that tells
         * an attendee anything.
         */
        $organiser = $this->signInWith(['employee', 'ceo'], 'EMP980');
        $attendee = $this->makeEmployee('EMP981');

        $this->post('/meetings', [
            'title' => 'Sprint planning',
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '10:00',
            'duration' => 30,
            'attendees' => [$attendee->user_id],
        ])->assertRedirect();

        $meeting = Meeting::firstOrFail();

        // The invite did not go out...
        $this->assertNull($meeting->event_id);

        // ...and the attendee was told anyway.
        $notification = Notification::where('user_id', $attendee->user_id)->firstOrFail();

        $this->assertStringContainsString('Sprint planning', $notification->body);
        $this->assertSame(0, Notification::where('user_id', $organiser->user_id)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE LINK
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_notification_for_a_route_that_does_not_exist_has_no_link(): void
    {
        /*
         * Decided 2026-08-28 and the reason the row stores a route name rather
         * than a URL: a link aimed at a page that has been renamed away is a
         * promise the application cannot keep, and it fails at the one moment
         * the notification was for.
         */
        $me = $this->signInWith(['employee'], 'EMP990');

        $notification = Notification::create([
            'user_id' => $me->user_id,
            'kind' => 'task',
            'title' => 'Something happened somewhere',
            'body' => 'On a page that is not there.',
            'link_route' => 'a.route.that.was.deleted',
            'link_params' => ['thing' => 'X-1'],
        ]);

        $this->assertNull($notification->link());

        // And the page draws the title as plain text rather than a dead anchor.
        $this->assertStringNotContainsString(
            '<a href="">Something happened somewhere</a>',
            $this->pageBody('/notifications'),
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE TABS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_unread_tab_counts_and_lists_only_the_unread(): void
    {
        $me = $this->signInWith(['employee'], 'EMP991');

        Notification::create([
            'user_id' => $me->user_id, 'kind' => 'task', 'title' => 'Still unread', 'body' => 'x',
        ]);

        Notification::create([
            'user_id' => $me->user_id,
            'kind' => 'task',
            'title' => 'Already seen',
            'body' => 'x',
            'read_at' => Carbon::now()->subDay(),
        ]);

        $all = $this->pageBody('/notifications');
        $this->assertStringContainsString('Still unread', $all);
        $this->assertStringContainsString('Already seen', $all);

        $unread = $this->pageBody('/notifications?tab=unread');
        $this->assertStringContainsString('Still unread', $unread);
        $this->assertStringNotContainsString('Already seen', $unread);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SEED
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_demo_seed_produces_only_rows_the_application_could_have_written(): void
    {
        /*
         * The seeder replays events through Notifier rather than writing rows,
         * so this asserts the two properties that follow from that and would be
         * lost the moment somebody "simplified" it into a fixture: every link
         * resolves, and nobody was told what they themselves did.
         */
        $this->seedDemoWorkforce();

        $notifications = Notification::query()->get();

        $this->assertNotEmpty($notifications, 'the demo seed produced no notifications');

        foreach ($notifications as $notification) {
            $this->assertNotNull(
                $notification->link(),
                $notification->title.' links nowhere, so the seed is pointing at a page that does not exist',
            );
        }

        $this->assertGreaterThan(
            0,
            $notifications->whereNull('read_at')->count(),
            'everything is seeded read, so the unread tab has nothing to show',
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function makeTask(array $overrides = []): Task
    {
        return Task::create($overrides + [
            'reference' => 'TSK-9'.fake()->unique()->numberBetween(10, 99),
            'name' => 'Wire the checkout error states',
            'status' => 'pending',
            'priority' => 'medium',
            'due_on' => Carbon::today()->addWeek()->toDateString(),
        ]);
    }

    protected function makeEmployee(string $staffId): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::where('role_key', 'employee')->pluck('id'));

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    /**
     * @param  list<string>  $roles
     */
    protected function signInWith(array $roles, string $staffId): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }
}
