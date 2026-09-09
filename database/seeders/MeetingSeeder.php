<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Project;
use App\Models\User;
use App\Support\Demo\DemoMeetings;
use Illuminate\Database\Seeder;

/**
 * The demo meetings and their attendees. Local + debug only.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE `event_id`s ARE THE FIXTURE'S, AND THEY ARE NOT REAL GOOGLE EVENTS
 *
 * `gcal_58x7a2` and friends are what the demo has always used, and they exist
 * so the scheduled/requested split is reviewable — a meeting with an event id
 * reads as scheduled, one without reads as requested and appears in the queue.
 *
 * The `join_url`s are the fixture's too, and they go in the column. That is not
 * a contradiction of "this application never invents a Meet link": the rule is
 * about the APPLICATION — nothing in app/ composes a URL from an event id, and
 * the column is filled only from what a provider returns. A local + debug
 * fixture standing in for that return value is the same fiction as its fake
 * emails and fake bank accounts.
 *
 * It also matters that they are here: the most important tests in this module
 * are the ones proving the link is withheld from somebody not on the invite,
 * and a seed with no links would make both of them pass by having nothing to
 * withhold.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MeetingSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        $accountsByStaffId = User::pluck('id', 'user_id');
        $clients = Client::pluck('id', 'name');
        $clientAccounts = User::whereNotNull('client_ref')->pluck('id', 'client_ref');
        $clientRefs = Client::pluck('reference', 'name');
        $projects = Project::pluck('id', 'reference');

        foreach (DemoMeetings::all() as $row) {
            $meeting = Meeting::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'title' => $row['title'],
                    'agenda' => $row['agenda'],
                    'project_id' => $row['project'] ? ($projects[$row['project']] ?? null) : null,
                    'organiser_id' => $row['organiser'] ? $employees->get($row['organiser'])?->id : null,
                    'requested_by_client_id' => $row['requested_by']
                        ? ($clients[$row['requested_by']] ?? null)
                        : null,
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'event_id' => $row['event_id'],
                    // The fixture's, standing in for what Google returned. See
                    // the head of this class.
                    'join_url' => $row['join_url'],
                    'html_link' => null,
                    'cancelled_at' => $row['cancelled_at'] ?? null,
                    'cancellation_reason' => $row['cancel_reason'] ?? null,
                ],
            );

            $meeting->attendees()->delete();

            foreach ($row['attendees'] as $attendee) {
                $userId = $attendee['kind'] === 'staff'
                    ? ($accountsByStaffId[$attendee['id']] ?? null)
                    : ($clientAccounts[$clientRefs[$attendee['id']] ?? ''] ?? null);

                if ($userId === null) {
                    continue;
                }

                MeetingAttendee::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $userId,
                    // Google's answer as the fixture records it. Nothing in the
                    // application writes one.
                    'response' => $attendee['response'],
                ]);
            }
        }
    }
}
