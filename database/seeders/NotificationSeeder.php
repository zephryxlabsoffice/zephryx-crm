<?php

namespace Database\Seeders;

use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Support\Notifier;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The demo bell. Local + debug only, and last of all the seeders.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * IT REPLAYS THE EVENTS RATHER THAN WRITING THE ROWS
 *
 * Every notification here comes out of `Notifier`, driven by records the other
 * seeders already created — this task, that leave decision, this meeting. Not
 * one of them is typed out.
 *
 * That is worth the extra work for three reasons:
 *
 *   - Every seeded row is one the application could actually have produced. A
 *     hand-written fixture drifts the moment the wording changes, and the
 *     screen a developer reviews stops being the screen a user will see.
 *   - Every link points at a record that exists, because the reference came off
 *     the record. The rule that a notification only links somewhere real is
 *     then true of the demo data too, rather than true only in production.
 *   - It exercises the rules. The seed cannot produce a notification telling
 *     somebody they assigned themselves a task, because `send()` drops it — the
 *     same code path that drops it on a live machine.
 *
 * NOTHING IS SEEDED IN PRODUCTION, AND THERE IS NO PRODUCTION SEEDER
 *
 * Unlike master data or roles, this table needs nothing in it for the
 * application to run. An empty bell on day one is correct: nothing has happened
 * yet.
 *
 * WHO ACTED IS TAKEN FROM THE RECORD, NEVER INVENTED
 *
 * The team lead assigned the task, the decider decided the leave, the organiser
 * called the meeting, the comment's author wrote the comment. Where a record
 * does not say — an unled team's task — the actor is null and the notification
 * reads "Somebody assigned you a task", which is what a live one would say in
 * the same situation rather than a name the seed made up.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class NotificationSeeder extends Seeder
{
    /**
     * Anything older than this is seeded as already read.
     *
     * A bell where everything is unread and a bell where nothing is are the
     * same screen: both hide the distinction the page is built around.
     */
    protected const READ_AFTER_HOURS = 36;

    public function __construct(protected Notifier $notify)
    {
    }

    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        // Idempotent like every other seeder, and the only place in the
        // application that deletes a notification. Re-running the seed must not
        // give a developer four copies of the same task assignment.
        Notification::query()->delete();

        $this->tasks();
        $this->leave();
        $this->tickets();
        $this->meetings();
    }

    /**
     * Assignments, credited to whoever would have made them.
     */
    protected function tasks(): void
    {
        $tasks = Task::query()
            ->has('assignees')
            ->with(['assignees.user', 'team.lead.user', 'project.manager.user'])
            ->get();

        foreach ($tasks as $task) {
            // The lead of the team holding the work, or the project's manager.
            // Exactly the two people `tasks.assign` is scoped to.
            $actor = $task->team?->lead?->user ?? $task->project?->manager?->user;

            $this->at(
                $task->updated_at ?? $task->created_at,
                fn () => $this->notify->taskAssigned($task, $task->assignees->pluck('user')->filter(), $actor),
            );
        }
    }

    /**
     * Decisions, credited to the decider the row already names.
     */
    protected function leave(): void
    {
        $decided = LeaveRequest::query()
            ->whereNotNull('decided_at')
            ->with(['employee.user', 'decider.user'])
            ->get();

        foreach ($decided as $request) {
            $this->at(
                $request->decided_at,
                fn () => $this->notify->leaveDecided($request, $request->decider?->user),
            );
        }
    }

    /**
     * Two events per ticket, and they are not the same event.
     *
     * The assignment is credited to whoever escalated it where the row says so,
     * and to nobody where it does not — triage leaves no other trace. Replies
     * are credited to their author, which the comment carries.
     */
    protected function tickets(): void
    {
        $tickets = Ticket::query()
            ->whereNotNull('assignee_id')
            ->with(['assignee.user', 'escalator.user'])
            ->get();

        foreach ($tickets as $ticket) {
            $this->at(
                $ticket->escalated_at ?? $ticket->updated_at ?? $ticket->created_at,
                fn () => $this->notify->ticketAssigned($ticket, null, $ticket->escalator?->user),
            );
        }

        $comments = TicketComment::query()
            ->with(['author', 'ticket.assignee.user', 'ticket.raiser.user'])
            ->get();

        foreach ($comments as $comment) {
            $ticket = $comment->ticket;

            if ($ticket === null) {
                continue;
            }

            $this->at(
                $comment->created_at,
                fn () => $this->notify->ticketCommented($ticket, $comment, $comment->author),
            );
        }
    }

    /**
     * Invites and cancellations, credited to the organiser.
     */
    protected function meetings(): void
    {
        $meetings = Meeting::query()->with('attendees.user')->get();

        foreach ($meetings as $meeting) {
            $organiser = $meeting->organiser?->user;

            $this->at(
                $meeting->created_at,
                fn () => $this->notify->meetingScheduled($meeting, $organiser),
            );

            if ($meeting->cancelled_at !== null) {
                $this->at(
                    $meeting->cancelled_at,
                    fn () => $this->notify->meetingCancelled(
                        $meeting,
                        (string) ($meeting->cancellation_reason ?? 'No reason given.'),
                        $organiser,
                    ),
                );
            }
        }
    }

    /**
     * Run one emission and date whatever it wrote.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WHY THE ROWS ARE FOUND BY ID RATHER THAN RETURNED
     *
     * `Notifier` returns nothing on purpose — a controller has no business
     * holding the notification it caused, and a return value is one more thing
     * a caller can be tempted to act on. So the seeder brackets the call and
     * takes what appeared, which also handles the methods that write several
     * rows at once (a meeting with four attendees) without any of them
     * changing shape for the seed's convenience.
     *
     * `saveQuietly` is not needed here — this is a bulk update, and there are
     * no model events on this table to suppress.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function at(?Carbon $when, Closure $emit): void
    {
        $when ??= now();

        $before = (int) Notification::query()->max('id');

        $emit();

        Notification::query()
            ->where('id', '>', $before)
            ->update([
                'created_at' => $when,
                'updated_at' => $when,
                // Read some minutes after it arrived, not on the hour it is
                // being seeded: `read_at` is when somebody saw a thing, and a
                // timestamp before `created_at` would be a row nothing could
                // have produced.
                'read_at' => $when->lt(now()->subHours(self::READ_AFTER_HOURS))
                    ? $when->copy()->addMinutes(7)
                    : null,
            ]);
    }
}
