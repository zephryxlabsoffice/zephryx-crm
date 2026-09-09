<?php

namespace App\Support\Audit;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The audit log (foundation spec §6).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WRITE-ONLY, FROM THE APPLICATION'S POINT OF VIEW
 *
 * This class has `record()` and nothing else. No update, no delete, no
 * truncate, no "prune old entries". The Admin Panel that reads it has no write
 * route in either direction either.
 *
 * That is not an oversight to be filled in later. An audit log the application
 * can edit is a log that says whatever the last person to reach the code wanted
 * it to say, and the actions most worth recording are exactly the ones somebody
 * would want to tidy away afterwards.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * WHAT AN ENTRY MUST CARRY, AND WHY
 *
 * §6 lists actor, action, entity, before/after, IP, user agent and timestamp.
 * The two that get skipped in practice are `before` and `after`, and they are
 * the two that matter: an entry recording that a value changed, without saying
 * from what to what, cannot answer the question anybody arrives with.
 *
 * For a settings change the `after` carries the EFFECT as well as the value —
 * "4 → 6, reclassified 47 days across 11 people" — because the new number alone
 * does not describe what happened to the records. See App\Support\Admin\
 * Retroactive.
 *
 * ACTIONS ARE NAMED, NOT FREE TEXT. The constants below are the vocabulary;
 * a caller passing an unlisted string still works, but the filters on the audit
 * page are built from these, so an ad-hoc action becomes an entry nobody finds.
 */
class AuditLog
{
    /* Authentication (§4) */
    public const SIGNED_IN = 'auth.signed_in';
    public const SIGN_IN_REFUSED = 'auth.sign_in_refused';
    public const SIGNED_OUT = 'auth.signed_out';
    public const OTP_ISSUED = 'auth.otp_issued';
    public const OTP_FAILED = 'auth.otp_failed';
    public const DEVICE_TRUSTED = 'auth.device_trusted';
    public const REMEMBER_THEFT = 'auth.remember_theft_detected';
    public const PASSWORD_RESET_REQUESTED = 'auth.password_reset_requested';
    public const PASSWORD_RESET = 'auth.password_reset';

    /*
     * Modules, added as their writes land.
     *
     * Constants rather than strings at the call site, because the audit screen
     * filters on `action` and a typo would create a second category that looks
     * like a real one and matches nothing.
     */
    public const EMPLOYEE_CREATED = 'employee.created';
    public const EMPLOYEE_UPDATED = 'employee.updated';
    public const EMPLOYEE_STATUS_CHANGED = 'employee.status_changed';

    public const CLIENT_CREATED = 'client.created';
    public const CLIENT_UPDATED = 'client.updated';
    public const CLIENT_STATUS_CHANGED = 'client.status_changed';
    /*
     * Portal access is its own action and not an update, because it is the one
     * that creates an ACCOUNT — the entry somebody looks for when asking who
     * could see this client's invoices, and when.
     */
    public const CLIENT_INVITED = 'client.invited';

    public const TEAM_CREATED = 'team.created';
    public const TEAM_UPDATED = 'team.updated';
    public const TEAM_STATUS_CHANGED = 'team.status_changed';
    /*
     * Joining and leaving share one action. Both answer the same question —
     * "who was on this team when" — and the entry carries which of the two it
     * was; two constants would split one timeline across two filters.
     */
    public const TEAM_MEMBERS_CHANGED = 'team.members_changed';

    public const PROJECT_CREATED = 'project.created';
    public const PROJECT_UPDATED = 'project.updated';
    public const PROJECT_STATUS_CHANGED = 'project.status_changed';
    /*
     * Posting an update and publishing one are two actions, not one with a
     * flag. "What was put in front of the client, and by whom" is the question
     * this log gets asked about a project, and it must be answerable by
     * filtering rather than by reading every entry's payload.
     */
    public const PROJECT_UPDATE_POSTED = 'project.update_posted';
    public const PROJECT_UPDATE_PUBLISHED = 'project.update_published';
    public const PROJECT_UPDATE_HIDDEN = 'project.update_hidden';

    public const TASK_CREATED = 'task.created';
    public const TASK_UPDATED = 'task.updated';
    /*
     * Assigning and completing are named separately from `task.updated`
     * because they are what the task's timeline is read for: who was put on
     * this, and when was it finished. Buried in an update entry, both become
     * questions somebody has to read a payload to answer.
     */
    public const TASK_ASSIGNED = 'task.assigned';
    public const TASK_COMPLETED = 'task.completed';
    public const TASK_REOPENED = 'task.reopened';

    /*
     * Attendance. The clock entries are here because §6 asks for them and
     * because a check-in somebody disputes is answered by the log rather than
     * by the record it wrote — the record says 09:21, the entry says who was
     * signed in and from which address when it said so.
     */
    public const ATTENDANCE_CHECKED_IN = 'attendance.checked_in';
    public const ATTENDANCE_CHECKED_OUT = 'attendance.checked_out';
    public const ATTENDANCE_REJECTED = 'attendance.rejected';
    public const ATTENDANCE_RESTORED = 'attendance.restored';

    /*
     * Leave. Approving and rejecting are separate actions and not one
     * "decided", because "who approved this, and who refused that" is the whole
     * question the log gets asked about leave.
     *
     * Note what the entries deliberately do NOT carry: the reason somebody gave
     * for asking. "Fever, seeing a doctor" is health information and belongs on
     * the request, in front of the approver, not in a log the Admin Panel lists
     * by the page.
     */
    public const LEAVE_REQUESTED = 'leave.requested';
    public const LEAVE_APPROVED = 'leave.approved';
    public const LEAVE_REJECTED = 'leave.rejected';
    public const LEAVE_CANCELLED = 'leave.cancelled';

    /*
     * Salary. The download is logged as well as the writes, and that is not
     * over-caution: a payslip is the one document in this application whose
     * having-been-read is itself the fact somebody may need to establish.
     */
    public const SALARY_PAYSLIP_ADDED = 'salary.payslip_added';
    public const SALARY_PAID = 'salary.paid';
    public const SALARY_PAYSLIP_DOWNLOADED = 'salary.payslip_downloaded';
    public const SALARY_BANKING_CHANGED = 'salary.banking_changed';

    public const TICKET_RAISED = 'ticket.raised';
    public const TICKET_COMMENTED = 'ticket.commented';
    public const TICKET_TRIAGED = 'ticket.triaged';

    /*
     * Invoices. Every one of these is a financial act, and the cancellation is
     * the one somebody will eventually be asked to account for — which is why
     * it carries its reason into the entry rather than only onto the record.
     */
    public const INVOICE_CREATED = 'invoice.created';
    public const INVOICE_SENT = 'invoice.sent';
    public const INVOICE_PAYMENT_RECORDED = 'invoice.payment_recorded';
    public const INVOICE_CANCELLED = 'invoice.cancelled';

    /* Admin Panel (§6 requires all of its actions) */
    public const PERMISSION_CHANGED = 'admin.permission_changed';
    public const SETTING_CHANGED = 'admin.setting_changed';
    public const ACCOUNT_CHANGED = 'admin.account_changed';
    public const MASTER_DATA_CHANGED = 'admin.master_data_changed';

    /**
     * Write one entry.
     *
     * Takes the actor explicitly rather than reading `auth()->user()`, because
     * the most important auth entries are written when there is no session yet
     * — a refused sign-in has an identifier and no user, and recording those as
     * "nobody" would lose the only trail an attempted break-in leaves.
     *
     * @param  array<string, mixed>|string|null  $before
     * @param  array<string, mixed>|string|null  $after
     */
    public function record(
        string $action,
        ?User $actor = null,
        ?string $actorLabel = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array|string|null $before = null,
        array|string|null $after = null,
        ?Request $request = null,
    ): void {
        $request ??= request();

        DB::table('audit_log')->insert([
            'actor_user_id' => $actor?->id,
            /*
             * The actor's name, copied at write time.
             *
             * Denormalised on purpose: `actor_user_id` is nullOnDelete, so an
             * entry whose account is later removed would otherwise read
             * "somebody changed a permission". The record of what happened has
             * to outlive the people in it.
             */
            'actor_label' => $actorLabel ?? $actor?->name ?? 'Unknown',
            'actor_type' => $actor?->account_type,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $this->encode($before),
            'after_json' => $this->encode($after),
            'ip_address' => $request?->ip(),
            // Truncated rather than dropped: a user agent is how somebody
            // recognises their own device in a list, and the column is bounded.
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 512) ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Everything that has happened to one thing, newest first.
     *
     * The `before_json` / `after_json` columns come back decoded to their
     * `summary` sentence, because that is what every screen showing history
     * renders. A caller wanting the whole payload can read the columns; nobody
     * currently does, and returning raw JSON to every view would put decoding
     * into templates.
     *
     * @return Collection<int, object>
     */
    public function entriesFor(string $entityType, string $entityId, int $limit = 20): Collection
    {
        return DB::table('audit_log')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (object $row) {
                $row->before_summary = $this->summaryOf($row->before_json);
                $row->after_summary = $this->summaryOf($row->after_json);
                $row->at = Carbon::parse($row->created_at);

                return $row;
            });
    }

    protected function summaryOf(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? ($decoded['summary'] ?? null) : null;
    }

    protected function encode(array|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode(is_string($value) ? ['summary' => $value] : $value);
    }
}
