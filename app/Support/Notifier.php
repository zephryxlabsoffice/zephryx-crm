<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * What writes to the bell.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * NOTHING TELLS YOU WHAT YOU JUST DID
 *
 * `send()` drops the row when the reader is the actor. It is the one rule every
 * method here relies on, and it is enforced in one place rather than at six
 * call sites, because the call site that forgets it is the one that assigns a
 * task to yourself — the most common thing anybody does with the task form —
 * and the result is a bell that is permanently unread with nothing in it worth
 * reading.
 *
 * AND TWO OF THE FOUR KINDS CAN BE SWITCHED OFF
 *
 * Tasks and tickets, from My Profile's preferences page. Leave decisions and
 * meeting invites cannot be — see `wants()`.
 *
 * NAMED EVENTS, NOT A MESSAGE BUS
 *
 * Every method below is one thing that happened, and the wording of the
 * notification lives here rather than at the call site. Six controllers each
 * composing their own sentence produces six voices in one list, and the day
 * "assigned" should read "reassigned" it has to be found in six places.
 *
 * WHAT DELIBERATELY DOES NOT NOTIFY
 *
 * Two of the six kinds the demo fixture carried are gone, and neither is an
 * omission:
 *
 *   - **Invoices.** "Payment received" has no addressee. Nothing in the
 *     invoices table records whose invoice it is — it belongs to the company —
 *     so the only honest recipient is "the finance team", which is a broadcast,
 *     and broadcasts go on the board.
 *
 *   - **"Due in five days" and "starts soon".** Those are questions about
 *     today, not events, and the answer changes on its own overnight. A row
 *     written when the project was created would be wrong by the time it was
 *     read, and there is no scheduler here to write one on the day. Like
 *     milestones on the board, they belong to whatever computes them — which is
 *     nothing, yet.
 *
 * A FAILED NOTIFICATION IS NOT CAUGHT
 *
 * Deliberately unlike the meeting provider, which is a network call to somebody
 * else's service and is allowed to fail. This is an insert on the same
 * connection as the write that caused it: if it cannot be written, neither
 * could the task, and swallowing the error would leave the two out of step in
 * the one direction nobody would think to check.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class Notifier
{
    /**
     * Write one row, unless there is nobody to write it to.
     *
     * @param  array<string, mixed>  $params
     */
    public function send(
        ?User $reader,
        string $kind,
        string $title,
        string $body,
        ?string $route = null,
        array $params = [],
        ?User $actor = null,
    ): void {
        if ($reader === null) {
            // An unassigned task, a meeting attendee whose account was removed.
            // Both are ordinary; neither is a notification.
            return;
        }

        if ($actor !== null && $actor->id === $reader->id) {
            return;
        }

        if (! $this->wants($reader, $kind)) {
            return;
        }

        Notification::create([
            'user_id' => $reader->id,
            'kind' => $kind,
            'title' => $title,
            // Trimmed to the column rather than refused. A body is a summary of
            // something the link goes to in full, and losing the last sentence
            // of a summary is better than losing the notification.
            'body' => Str::limit($body, 497),
            'link_route' => $route,
            'link_params' => $route === null ? null : $params,
        ]);
    }

    /**
     * Whether this reader has switched this kind off.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ONLY TWO KINDS HAVE A SWITCH, AND THAT IS THE DECISION
     *
     * Tasks and tickets are high-volume and about work in progress; somebody
     * who lives in those pages all day can reasonably say the bell adds
     * nothing. Leave decisions and meeting invites have no switch and will not
     * get one: they are how a decision reaches the person waiting for it, and
     * the preferences page says so rather than offering a control that quietly
     * loses them.
     *
     * A reader with no profile row — never opened the page — wants everything.
     * The default belongs in EmployeeProfile::blank(), not here, but the null
     * case is handled explicitly because a missing row must never read as
     * "switched off": the failure would be silent and would look like the
     * notifications simply not working.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function wants(User $reader, string $kind): bool
    {
        if ($kind !== 'task' && $kind !== 'ticket') {
            return true;
        }

        $profile = Employee::where('user_id', $reader->id)->first()?->profile;

        if ($profile === null) {
            return true;
        }

        return $kind === 'task' ? $profile->notify_tasks : $profile->notify_tickets;
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE EVENTS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Somebody was put on a task.
     *
     * Sent on every assignment, including a reassignment away from somebody
     * else — the person picking the work up is told; the person losing it is
     * not, because "no longer yours" is a thing they find out by looking at
     * their own list and is not something they have to act on.
     */
    public function taskAssigned(Task $task, ?User $actor = null): void
    {
        $this->send(
            reader: $task->assignee?->user,
            kind: 'task',
            title: ($actor?->name ?? 'Somebody').' assigned you a task',
            body: $task->name.' — due '.$task->due_on->format('D j M').'.',
            route: 'tasks.show',
            params: ['task' => $task->reference],
            actor: $actor,
        );
    }

    /**
     * A ticket came out of triage with somebody's name on it.
     *
     * Only when the assignee actually changed. Triage is one write covering
     * four decisions (priority, category, department, assignee), so re-saving
     * it to correct a category would otherwise notify the same person again
     * about a ticket they have had for a week.
     */
    public function ticketAssigned(Ticket $ticket, ?int $previousAssigneeId, ?User $actor = null): void
    {
        if ($ticket->assignee_id === null || $ticket->assignee_id === $previousAssigneeId) {
            return;
        }

        $escalated = $ticket->status === 'escalated';

        $this->send(
            reader: $ticket->assignee?->user,
            kind: 'ticket',
            title: $escalated ? 'Ticket escalated to you' : 'A ticket was assigned to you',
            body: $ticket->reference.' — '.$ticket->subject,
            route: 'tickets.show',
            params: ['ticket' => $ticket->reference],
            actor: $actor,
        );
    }

    /**
     * Somebody replied on a ticket.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE INTERNAL NOTE IS THE REASON THIS TAKES THE COMMENT AND NOT THE BODY
     *
     * A ticket has two staff readers — whoever raised it and whoever is on it —
     * and both are told, minus the author. But an internal note is invisible to
     * the person who raised a CLIENT ticket, and a notification quoting one
     * would put it in front of them anyway, in an email-shaped surface, which is
     * exactly the leak the visibility column exists to prevent.
     *
     * So the raiser of a client ticket is not a reader here at all — they have
     * no account on this side — and the note's text never leaves the thread:
     * the body says a reply arrived and the link goes to the ticket, where the
     * ordinary visibility rules apply again.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function ticketCommented(Ticket $ticket, TicketComment $comment, ?User $actor = null): void
    {
        $readers = collect([$ticket->assignee?->user, $ticket->raiser?->user])
            ->filter()
            ->unique('id');

        foreach ($readers as $reader) {
            $this->send(
                reader: $reader,
                kind: 'ticket',
                title: $comment->isInternal()
                    ? 'Internal note on a ticket you are on'
                    : 'New reply on a ticket you are on',
                // Never the comment text. See the head of this method.
                body: $ticket->reference.' — '.$ticket->subject,
                route: 'tickets.show',
                params: ['ticket' => $ticket->reference],
                actor: $actor,
            );
        }
    }

    /**
     * A leave request was approved or rejected.
     *
     * The one notification somebody is actively waiting for, which is why the
     * decision is in the title rather than the body: a bell showing "Your leave
     * request was updated" makes people open it to learn nothing.
     */
    public function leaveDecided(LeaveRequest $leave, ?User $actor = null): void
    {
        $approved = $leave->status === LeavePresenter::APPROVED;

        $this->send(
            reader: $leave->employee?->user,
            kind: 'leave',
            title: $approved ? 'Your leave was approved' : 'Your leave was not approved',
            body: Str::ucfirst(str_replace('_', ' ', (string) $leave->type))
                .', '.$leave->days.' '.Str::plural('day', (float) $leave->days)
                .($approved ? '.' : ' — '.$leave->decision_note),
            route: 'leave.show',
            params: ['leaveRequest' => $leave->reference],
            actor: $actor,
        );
    }

    /**
     * A meeting was put in the diary.
     *
     * Sent even though Google has already emailed everybody the invite, because
     * the calendar event is allowed to fail (see MeetingController::createEvent)
     * and this row is not. When the provider is down, this is the only thing
     * that tells the attendees anything at all.
     */
    public function meetingScheduled(Meeting $meeting, ?User $actor = null): void
    {
        foreach ($this->attendeesOf($meeting) as $reader) {
            $this->send(
                reader: $reader,
                kind: 'meeting',
                title: 'You were invited to a meeting',
                body: $meeting->title.' — '.$meeting->starts_at->format('D j M, H:i').'.',
                route: 'meetings.show',
                params: ['meeting' => $meeting->reference],
                actor: $actor,
            );
        }
    }

    /**
     * A meeting was called off.
     *
     * The reason is carried, unlike the ticket note above: it was written to be
     * read by exactly these people, and "cancelled" without it sends everybody
     * to the page to find out why.
     */
    public function meetingCancelled(Meeting $meeting, string $reason, ?User $actor = null): void
    {
        foreach ($this->attendeesOf($meeting) as $reader) {
            $this->send(
                reader: $reader,
                kind: 'meeting',
                title: 'A meeting was cancelled',
                body: $meeting->title.', '.$meeting->starts_at->format('D j M, H:i').' — '.$reason,
                route: 'meetings.show',
                params: ['meeting' => $meeting->reference],
                actor: $actor,
            );
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Everybody on the invite, organiser included.
     *
     * The organiser is not filtered out here: they are filtered out by `send()`
     * when they are the one acting, and they are a legitimate reader when
     * somebody else cancels their meeting.
     *
     * @return list<User>
     */
    protected function attendeesOf(Meeting $meeting): array
    {
        return $meeting->attendees()
            ->with('user')
            ->get()
            ->map(fn ($attendee) => $attendee->user)
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }
}
