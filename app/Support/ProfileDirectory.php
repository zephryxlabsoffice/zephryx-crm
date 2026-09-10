<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One person's own profile, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * EVERY METHOD TAKES THE PERSON, AND THERE IS NO DEFAULT
 *
 * The same shape as NotificationDirectory and for the same reason. There is no
 * `find()` with a fallback to "the current user" — a profile read that resolves
 * its own subject is one that can be made to resolve the wrong one by a caller
 * that forgot to pass anything.
 *
 * THE SUMMARY IS COMPUTED FROM THE MODULES THAT OWN IT
 *
 * Five figures, each read from the table that owns it and each linking to the
 * page it came from, so a number somebody disputes can be checked in one click.
 * A profile is a summary; the moment it becomes a second source it starts
 * disagreeing with the five pages it quotes, and then nobody trusts either.
 *
 * ACTIVITY IS THE AUDIT LOG, FILTERED TO ONE ACCOUNT
 *
 * Not a second log. §6 records the whole application and this page answers a
 * narrower question — "what has happened to my account" — which is what
 * somebody asks when they are worried. The mapping from audit actions to the
 * kinds the page draws is `ACTIVITY_KINDS` below; an action not in it does not
 * appear, because a profile page is not a window into the audit log.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ProfileDirectory
{
    /**
     * Audit actions this page shows, and the kind ProfilePresenter draws them
     * as.
     *
     * Deliberately short. Everything here is either something the person did to
     * their own account, or something done TO their record by somebody else —
     * which is the entry they should not first learn about from a payslip.
     *
     * Note what is absent: every module action. That somebody completed a task
     * is not "what happened to your account", and a page that listed it would
     * be an activity feed rather than the security screen this is.
     *
     * @var array<string, string>
     */
    public const ACTIVITY_KINDS = [
        Audit\AuditLog::SIGNED_IN => 'sign_in',
        Audit\AuditLog::SIGNED_OUT => 'sign_out',
        Audit\AuditLog::PASSWORD_RESET => 'password_changed',
        Audit\AuditLog::PROFILE_PASSWORD_CHANGED => 'password_changed',
        Audit\AuditLog::PROFILE_UPDATED => 'profile_updated',
        Audit\AuditLog::PROFILE_PREFERENCES_UPDATED => 'preferences_updated',
        Audit\AuditLog::PROFILE_EMAIL_CHANGED => 'email_changed',
        Audit\AuditLog::PROFILE_EMAIL_CHANGE_REQUESTED => 'email_changed',
        Audit\AuditLog::PROFILE_DOCUMENT_UPLOADED => 'document_uploaded',
        Audit\AuditLog::EMPLOYEE_UPDATED => 'hr_updated',
        Audit\AuditLog::EMPLOYEE_STATUS_CHANGED => 'hr_updated',
    ];

    /** How far back the activity page reads. */
    public const ACTIVITY_LIMIT = 40;

    /**
     * The whole profile: the employment record, the person's own fields on top,
     * and the sign-in facts the header draws.
     *
     * @return array<string, mixed>
     */
    public static function of(Employee $employee): array
    {
        $employee->loadMissing(['user', 'department', 'designation', 'profile', 'manager.user']);

        $own = $employee->profile?->toRecordArray() ?? EmployeeProfile::blank();

        return EmployeeDirectory::row($employee) + $own + [
            'manager_record' => $employee->manager
                ? EmployeeDirectory::row($employee->manager)
                : null,
            /*
             * The sign-in facts, all from `users` and none of them typed by
             * anybody — ProfilePolicy::SYSTEM. `username` is deliberately the
             * email: this application has one sign-in identifier and inventing
             * a second name for it on one screen would suggest otherwise.
             */
            'username' => $employee->user?->email,
            'email_verified_at' => $employee->user?->email_verified_at,
            'last_login_at' => $employee->user?->last_login_at,
            'password_changed_at' => $employee->user?->password_changed_at,
        ];
    }

    /**
     * The five figures on the rail.
     *
     * @return list<array{label: string, value: string, tone: string, route: string, icon: string}>
     */
    public static function summary(Employee $employee): array
    {
        $attendance = AttendancePolicy::monthSummary(
            Carbon::today()->startOfMonth(),
            AttendanceDirectory::forEmployee($employee),
            AttendanceDirectory::leaveDates($employee->id),
        );

        $balance = LeavePolicy::balance(LeaveDirectory::forEmployee($employee));

        return [
            [
                'label' => 'Projects you are on',
                'value' => (string) Project::query()->forEmployee($employee)->count(),
                'tone' => 'tone-accent',
                'route' => 'projects.mine',
                'icon' => 'projects',
            ],
            [
                /*
                 * Open, not "completed". A lifetime completed count reads zero
                 * until somebody has been here long enough for it not to, and a
                 * tile that is nearly always zero teaches people to skip the
                 * card. What is carried right now is also the more useful
                 * number.
                 */
                'label' => 'Tasks on your plate',
                'value' => (string) Task::query()
                    ->where('assignee_id', $employee->id)
                    ->whereNot('status', 'completed')
                    ->count(),
                'tone' => 'tone-soft',
                'route' => 'tasks.mine',
                'icon' => 'tasks',
            ],
            [
                'label' => 'Tickets you raised',
                'value' => (string) Ticket::query()->where('raised_by', $employee->id)->count(),
                'tone' => 'tone-alt',
                'route' => 'tickets.mine',
                'icon' => 'tickets',
            ],
            [
                // Days attended out of working days ELAPSED, which is what the
                // Attendance page says too. "22 / 22" on the 3rd of the month
                // was never true.
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

    /**
     * Files held against this person, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function documents(Employee $employee): Collection
    {
        return $employee->documents()
            ->get()
            ->map(fn (EmployeeDocument $d) => $d->toRecordArray($employee->user_id));
    }

    /**
     * The next document reference — from the highest existing one, never a
     * count. A count reuses a reference the moment anything is removed.
     */
    public static function nextDocumentReference(): string
    {
        $highest = EmployeeDocument::query()
            ->where('reference', 'like', 'DOC-%')
            ->selectRaw('max(cast(substr(reference, 5) as integer)) as n')
            ->value('n');

        return 'DOC-'.str_pad((string) (((int) $highest) + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * This account's own audit entries, newest first.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * SCOPED BY ENTITY, NOT BY ACTOR — AND THAT IS THE WHOLE POINT
     *
     * A filter on `actor_user_id` would show what this person DID. This page
     * has to show what happened TO them, which includes the entries where they
     * are not the actor: HR changing their designation, and — the one that
     * matters — a sign-in they did not make.
     *
     * So it reads entries whose entity is this account OR whose actor is, and
     * marks the ones that were not them. An activity page that only listed
     * somebody's own actions could never answer the question it exists for.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function activity(Employee $employee): Collection
    {
        $user = $employee->user;

        if ($user === null) {
            return collect();
        }

        $actions = array_keys(self::ACTIVITY_KINDS);

        return collect(DB::table('audit_log')
            ->whereIn('action', $actions)
            ->where(function ($q) use ($user) {
                $q->where('actor_user_id', $user->id)
                    ->orWhere(fn ($e) => $e->whereIn('entity_type', ['user', 'employee'])
                        ->where('entity_id', $user->user_id));
            })
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get())
            ->map(fn (object $row) => [
                'kind' => self::ACTIVITY_KINDS[$row->action],
                'detail' => self::detailOf($row),
                'by' => $row->actor_user_id === $user->id ? 'self' : ($row->actor_label ?? 'somebody else'),
                'at_time' => Carbon::parse($row->created_at),
            ])
            ->values();
    }

    /**
     * What one entry says on the page.
     *
     * The audit entry's `after` where there is one, because that is the field
     * §6 exists for — an entry saying a value changed without saying to what
     * cannot answer the question anybody arrives with. The action's label is
     * the fallback, never the first choice.
     */
    protected static function detailOf(object $row): string
    {
        $after = $row->after_json === null ? null : json_decode($row->after_json, true);

        if (is_string($after) && $after !== '') {
            return $after;
        }

        if (is_array($after) && $after !== []) {
            return implode(' · ', array_map(
                fn ($key, $value) => is_scalar($value) ? $key.': '.$value : $key,
                array_keys($after),
                $after,
            ));
        }

        return ProfilePresenter::activity(self::ACTIVITY_KINDS[$row->action])['label'];
    }

    /**
     * The employment record behind an account, or null.
     *
     * A Mentor and the owner hold no Employee base (§2.1), so they have no
     * profile to show — which the controller turns into a 403 rather than an
     * empty page pretending they have one.
     */
    public static function employeeFor(?User $user): ?Employee
    {
        return $user === null
            ? null
            : Employee::where('user_id', $user->id)->first();
    }
}
