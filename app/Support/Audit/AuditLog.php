<?php

namespace App\Support\Audit;

use App\Models\User;
use Illuminate\Http\Request;
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

    protected function encode(array|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode(is_string($value) ? ['summary' => $value] : $value);
    }
}
