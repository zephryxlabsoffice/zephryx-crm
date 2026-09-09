<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person on a meeting invite.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * `response` IS A CACHE OF GOOGLE'S ANSWER
 *
 * A person accepts or declines in their own calendar. This application reads
 * that back and displays it — there is no route, no button and no provider
 * method that sets one, and adding any of them would mean two systems claiming
 * to know whether somebody is coming.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MeetingAttendee extends Model
{
    protected $fillable = ['meeting_id', 'user_id', 'response'];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        return [
            'user_id' => $this->user?->user_id,
            'name' => (string) $this->user?->name,
            'email' => $this->user?->email,
            /*
             * `staff` or `client`, which is what the list draws differently —
             * a client on an internal-looking invite is worth being able to see
             * at a glance. Taken from the account's realm rather than stored,
             * because it is the same fact.
             */
            'kind' => $this->user?->account_type === 'client' ? 'client' : 'staff',
            // What to put under the name: a designation for staff, the company
            // for somebody at a client.
            'detail' => $this->user?->account_type === 'client'
                ? 'Client'
                : (string) $this->user?->display_role,
            'response' => $this->response,
        ];
    }
}
