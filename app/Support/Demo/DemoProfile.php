<?php

namespace App\Support\Demo;

use App\Support\AttendancePolicy;
use App\Support\LeavePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The signed-in person's own profile, for reviewing the My Profile pages before
 * the database exists. Local + debug only, like the other demo sources.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE EXTRA FIELDS LIVE HERE, NOT ON DemoEmployees
 *
 * Address, emergency contact, languages, skills and the rest are profile fields.
 * The Employees list does not show them, no other module reads them, and adding
 * eleven columns to the shared employee record to serve one page would mean
 * every list in the application carrying data it has no use for.
 *
 * They are keyed by employee and merged on top of the employee record, so the
 * shape a page receives is one profile rather than two objects it has to join.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NOTHING HERE IS INVENTED ACROSS MODULES
 *
 * The summary figures — projects, tasks, tickets, attendance, leave — are read
 * from the modules that own them (DemoProjects, DemoTasks, DemoTickets,
 * AttendancePolicy, LeavePolicy). The handover hardcoded 15 / 128 / 42 / 22 of
 * 22 / 12 days, and a profile that quotes five numbers none of which agree with
 * the five pages they came from is worse than a profile with no numbers on it.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoProfile
{
    /** The person the profile pages stand in for until authentication lands. */
    public const VIEWER = 'EMP002';

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function rows(): array
    {
        return [
            'EMP002' => [
                'phone' => '+91 98200 55667',
                'address' => "14B Southern Avenue\nKolkata, West Bengal 700029\nIndia",
                'gender' => 'Male',
                'marital_status' => 'Single',
                'nationality' => 'Indian',
                'languages' => ['English', 'Hindi', 'Bengali'],
                'skills' => ['Laravel', 'Vue', 'MySQL', 'Accessibility', 'Code review'],
                'emergency_name' => 'Sunita Verma',
                'emergency_relationship' => 'Mother',
                'emergency_phone' => '+91 98200 11002',
                'reports_to' => 'EMP001',
                'username' => 'amit.verma',
                'email_verified_at' => -540,
                'password_changed_at' => -42,
                'last_login_at' => 0,
            ],
        ];
    }

    /**
     * One person's profile — their employee record with the profile fields on
     * top, plus the derived dates.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $employeeId = self::VIEWER): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $employee = DemoEmployees::all()->firstWhere('user_id', $employeeId);

        if ($employee === null) {
            return null;
        }

        $profile = self::rows()[$employeeId] ?? [];

        $manager = ($profile['reports_to'] ?? null)
            ? DemoEmployees::all()->firstWhere('user_id', $profile['reports_to'])
            : null;

        // array_merge for the resolved dates, NOT `+`. Union keeps the LEFT
        // side's value for a key that already exists, so the raw day offsets in
        // `$profile` would survive and the Carbon values below would be thrown
        // away — leaving `Carbon::parse(0)` to blow up in the view.
        return array_merge(
            $employee + $profile + [
                'languages' => [],
                'skills' => [],
                'phone' => null,
                'address' => null,
                'gender' => null,
                'marital_status' => null,
                'nationality' => null,
                'emergency_name' => null,
                'emergency_relationship' => null,
                'emergency_phone' => null,
                'username' => null,
            ],
            [
                'manager_record' => $manager,
                // Offsets rather than dates, for the reason every demo source
                // uses them: fixed dates make every screen look abandoned
                // within weeks.
                'email_verified_at' => self::at($profile['email_verified_at'] ?? null),
                'password_changed_at' => self::at($profile['password_changed_at'] ?? null),
                'last_login_at' => self::at($profile['last_login_at'] ?? null, 8, 42),
            ],
        );
    }

    protected static function at(?int $offset, int $hour = 9, int $minute = 15): ?Carbon
    {
        return $offset === null ? null : Carbon::today()->addDays($offset)->setTime($hour, $minute);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SUMMARY — READ FROM THE MODULES THAT OWN IT
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * The five figures on the rail, each with the page it came from.
     *
     * Every one is computed from the module that owns it and links there, so a
     * number somebody disputes can be checked in one click rather than argued
     * about. A profile is a summary; it must not become a second source.
     *
     * @return list<array{label: string, value: string, tone: string, route: string, icon: string}>
     */
    public static function summary(string $employeeId = self::VIEWER): array
    {
        if (! self::enabled()) {
            return [];
        }

        $tasks = DemoTasks::mine($employeeId);
        $attendance = AttendancePolicy::monthSummary(
            Carbon::today()->startOfMonth(),
            DemoAttendance::forEmployee($employeeId),
            DemoAttendance::leaveDates($employeeId),
        );
        $balance = LeavePolicy::balance(DemoLeave::forEmployee($employeeId));

        return [
            [
                'label' => 'Projects you are on',
                'value' => (string) DemoProjects::mine($employeeId)->count(),
                'tone' => 'tone-accent',
                'route' => 'projects.mine',
                'icon' => 'projects',
            ],
            [
                // Open, not "completed". A lifetime completed count is the
                // handover's 128, and on a real record it reads zero until
                // somebody has been here long enough for it not to — a tile
                // that is nearly always zero teaches people to skip the card.
                // What is carried right now is also the more useful number.
                'label' => 'Tasks on your plate',
                'value' => (string) $tasks->where('status', '!=', 'completed')->count(),
                'tone' => 'tone-soft',
                'route' => 'tasks.mine',
                'icon' => 'tasks',
            ],
            [
                'label' => 'Tickets you raised',
                'value' => (string) DemoTickets::raisedBy($employeeId)->count(),
                'tone' => 'tone-alt',
                'route' => 'tickets.mine',
                'icon' => 'tickets',
            ],
            [
                // Days attended out of working days ELAPSED, which is what the
                // Attendance page says too. "22 / 22" on the 3rd of the month
                // is the handover's version and it was never true.
                'label' => 'Attended this month',
                'value' => $attendance['attended'].' / '.$attendance['working_days'],
                'tone' => 'tone-warn',
                'route' => 'attendance.mine',
                'icon' => 'attendance',
            ],
            [
                'label' => 'Leave left this year',
                'value' => $balance['remaining'].' of '.$balance['entitlement'].' days',
                'tone' => 'tone-soft',
                'route' => 'leave.mine',
                'icon' => 'leave',
            ],
        ];
    }

    /* ══════════════════════════════════════════════════════════════════════
       DOCUMENTS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Files held against a person.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE MOST SENSITIVE THING ON THIS PAGE
     *
     * A PAN or Aadhaar scan is not the same class of data as a masked number.
     * App\Support\Sensitive keeps the NUMBERS off screens; this is the card
     * itself, photograph and all, in a file that can be downloaded once and
     * then exists somewhere nobody is tracking.
     *
     * So the list shows what is held and nothing more. Sizes and dates, no
     * previews, no thumbnails. What the backend owes is in the partial that
     * renders this (resources/views/profile/partials/documents.blade.php) and
     * it is not optional: files outside the webroot, short-lived signed URLs,
     * an audit entry per download, and no route by which anyone but the person
     * themselves and HR reaches them.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function documents(string $employeeId = self::VIEWER): Collection
    {
        if (! self::enabled() || $employeeId !== self::VIEWER) {
            return collect();
        }

        return collect([
            ['id' => 'DOC-0011', 'name' => 'Resume.pdf', 'kind' => 'resume', 'bytes' => 251_904, 'uploaded' => -540, 'uploaded_by' => 'self'],
            ['id' => 'DOC-0012', 'name' => 'PAN card.pdf', 'kind' => 'identity', 'bytes' => 163_840, 'uploaded' => -538, 'uploaded_by' => 'self'],
            ['id' => 'DOC-0013', 'name' => 'Aadhaar card.pdf', 'kind' => 'identity', 'bytes' => 327_680, 'uploaded' => -538, 'uploaded_by' => 'self'],
            ['id' => 'DOC-0014', 'name' => 'Offer letter.pdf', 'kind' => 'employment', 'bytes' => 98_304, 'uploaded' => -545, 'uploaded_by' => 'hr'],
        ])->map(fn (array $row) => $row + [
            'uploaded_at' => Carbon::today()->addDays($row['uploaded']),
        ])->values();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ACTIVITY
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * This person's own audit entries, newest first.
     *
     * Only theirs. The audit log (§6) records the whole application, and a
     * profile page is not a window into it — it answers "what has happened to
     * my account", which is a question somebody asks when they are worried.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function activity(string $employeeId = self::VIEWER): Collection
    {
        if (! self::enabled() || $employeeId !== self::VIEWER) {
            return collect();
        }

        return collect([
            ['kind' => 'sign_in', 'detail' => 'Signed in', 'by' => 'self', 'at' => [0, 8, 42]],
            ['kind' => 'preferences_updated', 'detail' => 'Switched to the dark theme', 'by' => 'self', 'at' => [-2, 17, 4]],
            ['kind' => 'sign_in', 'detail' => 'Signed in', 'by' => 'self', 'at' => [-2, 9, 12]],
            ['kind' => 'profile_updated', 'detail' => 'Changed your emergency contact number', 'by' => 'self', 'at' => [-9, 11, 30]],
            // Something HR did to the record, shown because it is the person's
            // record and they should not learn about it from a payslip.
            ['kind' => 'hr_updated', 'detail' => 'Designation changed to Frontend Developer by Pooja Singh', 'by' => 'EMP005', 'at' => [-24, 15, 20]],
            ['kind' => 'password_changed', 'detail' => 'You changed your password', 'by' => 'self', 'at' => [-42, 10, 6]],
            ['kind' => 'document_uploaded', 'detail' => 'Uploaded Resume.pdf', 'by' => 'self', 'at' => [-540, 12, 0]],
        ])->map(fn (array $row) => $row + [
            'at_time' => Carbon::today()->addDays($row['at'][0])->setTime($row['at'][1], $row['at'][2]),
        ])->values();
    }
}
