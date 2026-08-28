<?php

namespace App\Support\Demo;

use App\Support\MeetingPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample meetings, for reviewing the Meetings pages before the database exists.
 * Local + debug only, like the other demo sources.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT A MEETING RECORD IS
 *
 * Who is meeting, about what, when — plus a reference to the Google event that
 * actually hosts it. `event_id` and `join_url` come back FROM Google; nothing
 * here invents them, and a meeting with neither has not been created yet.
 *
 * Attendee responses are Google's. They are read back and displayed; this
 * application never sets one.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TIMES ARE UTC
 *
 * Every `starts_at` and `ends_at` below is UTC, and only App\Support\
 * MeetingPresenter turns one into something a person reads. The offsets are
 * relative to now so the pages have something live to show — the handover's
 * were all in May 2024, which makes every meeting "ended" and the upcoming
 * list impossible to review.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoMeetings
{
    /** The person the "my" views stand in for until authentication lands. */
    public const VIEWER = 'EMP002';

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * `day` is days from today and `at` is the start time in the display zone —
     * meetings happen in working hours, not at whatever o'clock the page is
     * being reviewed. `mins` is how long it runs.
     *
     * One row uses `in` instead: hours from now, so there is always something
     * imminent on screen and the "happening soon" states are reviewable.
     *
     * @return list<array<string, mixed>>
     */
    protected static function rows(): array
    {
        return [
            [
                'id' => 'MTG-2026-058', 'title' => 'Sprint planning', 'project' => 'WD-2024-001',
                'in' => 0.4, 'mins' => 45, 'organiser' => 'EMP002',
                'agenda' => 'Scope for the next two weeks and who picks up the CMS migration.',
                'staff' => ['EMP002' => 'accepted', 'EMP001' => 'accepted', 'EMP004' => 'awaiting'],
                'clients' => [],
                'event_id' => 'gcal_58x7a2', 'requested_by' => null, 'cancelled' => false, 'cancel_reason' => null,
            ],
            [
                'id' => 'MTG-2026-057', 'title' => 'Storefront walkthrough', 'project' => 'ST-2024-011',
                'day' => 1, 'at' => '11:00', 'mins' => 60, 'organiser' => 'EMP006',
                'agenda' => 'Demo of the checkout flow before it goes to staging.',
                'staff' => ['EMP006' => 'accepted', 'EMP002' => 'accepted'],
                'clients' => ['Urban Nest Interiors' => 'accepted'],
                'event_id' => 'gcal_57b9c4', 'requested_by' => null, 'cancelled' => false, 'cancel_reason' => null,
            ],
            [
                'id' => 'MTG-2026-056', 'title' => 'Payment gateway issue', 'project' => 'EC-2024-005',
                'day' => 2, 'at' => '15:30', 'mins' => 30, 'organiser' => 'EMP006',
                'agenda' => 'Card payments failing at the final step — walk through the logs together.',
                'staff' => ['EMP006' => 'accepted', 'EMP004' => 'accepted'],
                'clients' => ['MediCare Services' => 'awaiting'],
                'event_id' => 'gcal_56d1f8', 'requested_by' => 'MediCare Services', 'cancelled' => false, 'cancel_reason' => null,
            ],
            [
                'id' => 'MTG-2026-055', 'title' => 'Monthly check-in', 'project' => 'SMC-2024-002',
                'day' => 4, 'at' => '10:00', 'mins' => 30, 'organiser' => 'EMP003',
                'agenda' => 'Campaign performance and next month’s plan.',
                'staff' => ['EMP003' => 'accepted'],
                'clients' => ['GreenLeaf Foods' => 'tentative'],
                'event_id' => 'gcal_55a3e7', 'requested_by' => null, 'cancelled' => false, 'cancel_reason' => null,
            ],
            // ── requested, nothing on Google yet ──
            // A client asked. Clients cannot create a meeting; a project manager
            // or the system admin creates it, which is what produces the Google
            // event and the link (decided 2026-08-28).
            [
                'id' => 'MTG-2026-054', 'title' => 'Invoice query', 'project' => 'CRM-2024-003',
                'day' => 5, 'at' => '16:00', 'mins' => 30, 'organiser' => 'EMP002',
                'agenda' => 'The last invoice applied 18% tax where we expected 5%.',
                'staff' => ['EMP002' => 'awaiting'],
                'clients' => ['TechNova Solutions' => 'awaiting'],
                'event_id' => null, 'requested_by' => 'TechNova Solutions', 'cancelled' => false, 'cancel_reason' => null,
            ],
            [
                'id' => 'MTG-2026-053', 'title' => 'Learning platform scope', 'project' => 'LMS-2024-009',
                'day' => 7, 'at' => '14:00', 'mins' => 60, 'organiser' => 'EMP002',
                'agenda' => 'Phase two requirements before we quote.',
                'staff' => ['EMP002' => 'awaiting', 'EMP001' => 'awaiting'],
                'clients' => ['Bright Future Academy' => 'awaiting'],
                'event_id' => null, 'requested_by' => 'Bright Future Academy', 'cancelled' => false, 'cancel_reason' => null,
            ],
            // ── past ──
            [
                'id' => 'MTG-2026-052', 'title' => 'Design review', 'project' => 'BR-2024-004',
                'day' => -1, 'at' => '15:00', 'mins' => 45, 'organiser' => 'EMP001',
                'agenda' => 'Logo suite, second round.',
                'staff' => ['EMP001' => 'accepted', 'EMP012' => 'accepted'],
                'clients' => ['ABC Pvt Ltd' => 'accepted'],
                'event_id' => 'gcal_52c8b1', 'requested_by' => null, 'cancelled' => false, 'cancel_reason' => null,
            ],
            [
                'id' => 'MTG-2026-051', 'title' => 'Weekly stand-up', 'project' => null,
                'day' => -3, 'at' => '09:30', 'mins' => 15, 'organiser' => 'EMP002',
                'agenda' => 'Internal. What everyone is on this week.',
                'staff' => ['EMP002' => 'accepted', 'EMP001' => 'accepted', 'EMP004' => 'accepted', 'EMP006' => 'declined'],
                'clients' => [],
                'event_id' => 'gcal_51f4d9', 'requested_by' => null, 'cancelled' => false, 'cancel_reason' => null,
            ],
            [
                'id' => 'MTG-2026-050', 'title' => 'Fleet tracking kickoff', 'project' => 'FL-2024-010',
                'day' => -6, 'at' => '11:30', 'mins' => 60, 'organiser' => 'EMP004',
                'agenda' => 'Introductions and discovery.',
                'staff' => ['EMP004' => 'accepted'],
                'clients' => ['Sunrise Logistics' => 'accepted'],
                'event_id' => 'gcal_50e2a6', 'requested_by' => null, 'cancelled' => false, 'cancel_reason' => null,
            ],
            // ── cancelled ──
            [
                'id' => 'MTG-2026-049', 'title' => 'SEO retainer review', 'project' => 'SEO-2024-008',
                'day' => 3, 'at' => '12:00', 'mins' => 30, 'organiser' => 'EMP003',
                'agenda' => 'Quarterly performance.',
                'staff' => ['EMP003' => 'accepted'],
                'clients' => ['DGL International School' => 'awaiting'],
                'event_id' => 'gcal_49b7c3', 'requested_by' => null, 'cancelled' => true,
                'cancel_reason' => 'Client asked to move it to after their term ends.',
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $employees = DemoEmployees::all()->keyBy('user_id');
        $projects = DemoProjects::all()->keyBy('id');

        return collect(self::rows())->map(function (array $row) use ($employees, $projects) {
            // A row is either anchored to a working hour in the display zone,
            // or offset from now for the one that has to be imminent. Either
            // way it is converted to UTC here — nothing downstream ever sees a
            // local time it might mistake for a stored one.
            $starts = isset($row['at'])
                ? Carbon::parse(
                    Carbon::today(MeetingPresenter::zone())->addDays($row['day'])->format('Y-m-d').' '.$row['at'],
                    MeetingPresenter::zone()
                )->setTimezone('UTC')
                : Carbon::now('UTC')->addMinutes((int) round($row['in'] * 60))->startOfMinute();

            $ends = $starts->copy()->addMinutes($row['mins']);

            $attendees = [];

            foreach ($row['staff'] as $id => $response) {
                $employee = $employees->get($id);

                if ($employee === null) {
                    continue;
                }

                $attendees[] = [
                    'kind' => 'staff',
                    'id' => $id,
                    'name' => $employee['name'],
                    'detail' => $employee['designation'],
                    'email' => $employee['email'],
                    'response' => $response,
                    'organiser' => $id === $row['organiser'],
                ];
            }

            foreach ($row['clients'] as $name => $response) {
                $attendees[] = [
                    'kind' => 'client',
                    'id' => $name,
                    'name' => $name,
                    'detail' => 'Client',
                    'email' => null,
                    'response' => $response,
                    'organiser' => false,
                ];
            }

            $meeting = $row + [
                'starts_at' => $starts->toDateTimeString(),
                'ends_at' => $ends->toDateTimeString(),
                'organiser_record' => $employees->get($row['organiser']),
                'project_record' => $row['project'] ? $projects->get($row['project']) : null,
                'attendees' => $attendees,
                // Only ever what Google gave back. A link is never assembled
                // from an event id — see App\Support\Meetings\GoogleMeetProvider.
                'join_url' => $row['event_id'] ? 'https://meet.google.com/'.self::linkFor($row['event_id']) : null,
                'cancelled_at' => $row['cancelled'] ? $starts->copy()->subDays(2)->toDateTimeString() : null,
                'created_at' => $starts->copy()->subDays(3)->toDateTimeString(),
            ];

            $meeting['status'] = MeetingPresenter::statusOf($meeting);

            return $meeting;
        })->values();
    }

    /**
     * A stable, obviously-fake Meet code for the sample data.
     *
     * Deliberately not a realistic-looking one: these must never be mistaken
     * for links that work, and nothing outside local + debug ever sees them.
     */
    protected static function linkFor(string $eventId): string
    {
        return 'demo-'.substr($eventId, 5, 4).'-only';
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /**
     * Whether somebody is on the invite.
     *
     * The join link is shown to attendees and nobody else (decided 2026-08-28):
     * a Meet link is effectively a password, and a call about somebody's salary
     * or a client dispute should not be walk-into-able by a colleague browsing
     * the list.
     *
     * @param  array<string, mixed>  $meeting
     */
    public static function isAttendee(array $meeting, string $employeeId): bool
    {
        foreach ($meeting['attendees'] as $attendee) {
            if ($attendee['kind'] === 'staff' && $attendee['id'] === $employeeId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function upcoming(): Collection
    {
        return self::all()
            ->filter(fn (array $m) => $m['status'] === MeetingPresenter::SCHEDULED)
            ->sortBy('starts_at')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function requested(): Collection
    {
        return self::all()->where('status', MeetingPresenter::REQUESTED)->sortBy('starts_at')->values();
    }

    /**
     * The next meeting the viewer is actually on.
     *
     * Not simply the next meeting in the company: a card headed "Next meeting"
     * showing one somebody is not invited to is worse than showing nothing.
     *
     * @return array<string, mixed>|null
     */
    public static function nextFor(string $employeeId): ?array
    {
        return self::upcoming()
            ->first(fn (array $m) => self::isAttendee($m, $employeeId));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $meetings
     * @return array<string, int>
     */
    public static function stats(Collection $meetings): array
    {
        $countOf = fn (string $status) => $meetings->where('status', $status)->count();

        return [
            'total' => $meetings->count(),
            'requested' => $countOf(MeetingPresenter::REQUESTED),
            'scheduled' => $countOf(MeetingPresenter::SCHEDULED),
            'ended' => $countOf(MeetingPresenter::ENDED),
            'cancelled' => $countOf(MeetingPresenter::CANCELLED),
        ];
    }
}
