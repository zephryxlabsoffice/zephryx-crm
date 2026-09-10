<?php

namespace App\Support\Auth;

use App\Models\EmailChange;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Changing the sign-in address (§4.1).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * BOTH ADDRESSES CONFIRM, AND NEITHER ONE ALONE IS ENOUGH
 *
 * The email is the login identifier. A form that writes a new one straight onto
 * the account is an account-takeover primitive: anybody with a borrowed session
 * points it at their own address, and the real owner is locked out of a system
 * that no longer knows how to reach them.
 *
 * So a change carries two tokens and moves nothing until both are spent:
 *
 *   THE OLD ADDRESS proves the person asking holds the account. Without it, a
 *   borrowed session is the whole attack.
 *
 *   THE NEW ADDRESS proves it is one they can actually receive at. Without it,
 *   a typo — or somebody typing a colleague's address — locks the account to a
 *   mailbox its owner cannot read, which is the same outcome as the attack and
 *   is far more likely to happen by accident.
 *
 * Until both are in, `users.email` is untouched and the person keeps signing in
 * with the address they have always used. That is the property worth stating:
 * an abandoned change costs nothing and locks nobody out.
 *
 * THE TOKEN CARRIES THE CHANGE, AND NOTHING ELSE IS TRUSTED
 *
 * `confirm()` takes a token and nothing more — no user id, no address, no
 * "which half". The same rule PasswordResets holds to, for the same reason: a
 * confirm route that accepted an identifier alongside the token would let
 * anybody with a token for their own change confirm somebody else's by editing
 * one field.
 *
 * Which half a token is for is decided by which column it matches, so a link
 * mailed to the old address cannot be used to satisfy the new one — the trick
 * that would otherwise turn "confirm from both" back into "confirm from one".
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmailChanges
{
    /**
     * Longer than a password reset's hour, and deliberately.
     *
     * Two people have to act, one of them possibly on a mailbox they only open
     * at home. An hour would send most changes round twice, and the second
     * attempt teaches somebody that the first link "did not work".
     */
    public const EXPIRY_HOURS = 24;

    /** Which half of a change a token confirms. */
    public const OLD = 'old';

    public const NEW = 'new';

    /**
     * Start a change, returning the two plaintext tokens for the two mails.
     *
     * Any change already in flight for this account is abandoned first. Two
     * live changes at once means the address the account lands on is whichever
     * pair of links somebody happens to click, which is not a decision anybody
     * made.
     *
     * @return array{change: EmailChange, old: string, new: string}
     */
    public function start(User $user, string $newEmail, Request $request): array
    {
        $this->cancelAllFor($user);

        $old = Str::random(64);
        $new = Str::random(64);

        $change = EmailChange::create([
            'user_id' => $user->id,
            'new_email' => $newEmail,
            'old_token_hash' => $this->hash($old),
            'new_token_hash' => $this->hash($new),
            'expires_at' => now()->addHours(self::EXPIRY_HOURS),
            'ip_address' => $request->ip(),
        ]);

        return ['change' => $change, 'old' => $old, 'new' => $new];
    }

    /**
     * Spend one half of a change.
     *
     * Returns the change with that half confirmed, or null if the token matches
     * nothing live. Idempotent per half: clicking the same link twice is a
     * person checking, not a second confirmation, and it must not read as an
     * error on a page that has already done what they asked.
     */
    public function confirm(string $token): ?EmailChange
    {
        $hash = $this->hash($token);

        $change = EmailChange::query()
            ->live()
            ->where(fn ($q) => $q->where('old_token_hash', $hash)->orWhere('new_token_hash', $hash))
            ->orderByDesc('id')
            ->first();

        if ($change === null) {
            return null;
        }

        // Which half is decided by the column, never by anything in the
        // request. See the head of this class.
        $half = $change->old_token_hash === $hash ? self::OLD : self::NEW;

        $column = $half.'_confirmed_at';

        if ($change->{$column} === null) {
            $change->forceFill([$column => now()])->save();
        }

        return $change;
    }

    /**
     * Apply a fully-confirmed change. Returns the address it moved from.
     *
     * The address is written and the verification stamp is set in the same
     * save: the new address has just proved it receives mail, which is exactly
     * what `email_verified_at` records. Leaving it null would mark somebody
     * unverified moments after they verified.
     */
    public function apply(EmailChange $change): ?string
    {
        if (! $change->isConfirmed() || $change->isSpent()) {
            return null;
        }

        $user = $change->user;

        if ($user === null) {
            return null;
        }

        $was = $user->email;

        $user->forceFill([
            'email' => $change->new_email,
            'email_verified_at' => now(),
        ])->save();

        $change->forceFill(['completed_at' => now()])->save();

        return $was;
    }

    /**
     * The change in flight for an account, if there is one.
     */
    public function liveFor(User $user): ?EmailChange
    {
        return EmailChange::query()->live()->where('user_id', $user->id)->orderByDesc('id')->first();
    }

    public function cancelAllFor(User $user): void
    {
        EmailChange::query()
            ->live()
            ->where('user_id', $user->id)
            ->update(['cancelled_at' => now(), 'updated_at' => now()]);
    }

    /**
     * A fast hash, not bcrypt — the same reasoning as PasswordResets: 64
     * characters from a CSPRNG have nothing to guess, so a slow hash buys
     * nothing, and what matters is that the stored value cannot be turned back
     * into a working link.
     */
    protected function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
