<?php

namespace App\Models;

use App\Support\MeetingPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A meeting this application organised and Google hosts.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * `join_url` NEVER LEAVES THIS CLASS UNCONDITIONALLY
 *
 * `toRecordArray()` takes the viewer and withholds the link from anybody not on
 * the invite. A Meet link is effectively a password, and the withholding is in
 * PHP because anything the browser receives has already been read by whoever is
 * at the browser.
 *
 * There is deliberately no accessor that returns it without being asked whose
 * behalf it is for.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class Meeting extends Model
{
    protected $fillable = [
        'reference', 'title', 'agenda', 'project_id', 'organiser_id',
        'requested_by_client_id', 'starts_at', 'ends_at',
        'event_id', 'join_url', 'html_link', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'organiser_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function requestedByClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'requested_by_client_id');
    }

    /**
     * @return HasMany<MeetingAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function isScheduled(): bool
    {
        return $this->event_id !== null && $this->cancelled_at === null;
    }

    /**
     * Whether this account is on the invite.
     *
     * The organiser counts even if nobody added them as an attendee row: they
     * called the meeting, and a link they cannot see is a meeting they cannot
     * join.
     */
    public function isAttendedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->organiser?->user_id === $user->id) {
            return true;
        }

        return $this->attendees->contains(fn (MeetingAttendee $a) => $a->user_id === $user->id);
    }

    /**
     * The row shape the presenter and the views read.
     *
     * The viewer is REQUIRED, because the join link depends on it — see the
     * head of this class.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(?User $viewer): array
    {
        $meeting = [
            'id' => $this->reference,
            'title' => $this->title,
            'agenda' => $this->agenda,
            'project' => $this->project?->reference,
            'organiser' => $this->organiser?->user?->user_id,
            'requested_by' => $this->requestedByClient?->name,
            // UTC, always. Only MeetingPresenter turns one into a local time.
            'starts_at' => $this->starts_at->toDateTimeString(),
            'ends_at' => $this->ends_at->toDateTimeString(),
            'event_id' => $this->event_id,
            'html_link' => $this->html_link,
            'cancelled_at' => $this->cancelled_at,
            'cancelled' => $this->cancelled_at !== null,
            'cancel_reason' => $this->cancellation_reason,
            // The two records the views draw beside the meeting.
            'organiser_record' => $this->organiser
                ? \App\Support\EmployeeDirectory::row($this->organiser)
                : null,
            'project_record' => $this->project
                ? \App\Support\ProjectDirectory::row($this->project)
                : null,
            'attendees' => $this->attendees
                ->map(fn (MeetingAttendee $a) => $a->toRecordArray()
                    + ['organiser' => $a->user_id === $this->organiser?->user_id])
                ->all(),
            // Withheld unless this viewer is on the invite.
            'join_url' => $this->isAttendedBy($viewer) ? $this->join_url : null,
        ];

        $meeting['status'] = MeetingPresenter::statusOf($meeting);

        return $meeting;
    }

    /**
     * Meetings still to happen and not called off.
     *
     * @param  Builder<Meeting>  $query
     * @return Builder<Meeting>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        /*
         * `now()->utc()`, not `now()`.
         *
         * The application's timezone is the office's — Asia/Kolkata — and these
         * columns are UTC. Comparing the column against a local `now()` puts
         * every meeting of the next five and a half hours in the past, which is
         * the same class of mistake MeetingPresenter::statusOf carries a note
         * about. The stored value decides the comparison, so the comparison has
         * to be in the stored value's zone.
         */
        return $query->whereNull('cancelled_at')->where('ends_at', '>=', now()->utc());
    }

    /**
     * Asked for by a client and not yet created on Google.
     *
     * @param  Builder<Meeting>  $query
     * @return Builder<Meeting>
     */
    public function scopeRequested(Builder $query): Builder
    {
        return $query->whereNull('event_id')->whereNull('cancelled_at');
    }
}
