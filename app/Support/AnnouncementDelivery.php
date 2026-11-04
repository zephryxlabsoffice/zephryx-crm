<?php

namespace App\Support;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Telling people when an announcement goes on the board.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONCE, WHEN IT GOES UP
 *
 * Posting marks the row `delivery_pending`. A post going up today is delivered
 * straight away. A scheduled one waits for its start date, and is picked up by
 * the nightly job or by the first staff page anybody opens that day, whichever
 * comes first: there is no other timer on this hosting. Clearing the flag claims
 * the row, so two requests at once cannot both send.
 *
 * WHO HEARS WHAT
 *
 *   Staff it is addressed to    the bell and push, always; email if ticked
 *   Clients, if `for_clients`   the bell on their portal, nothing else
 *
 * Whoever pressed Post is left out: nothing tells you what you just did.
 *
 * THE BELL IS WRITTEN FIRST, THE REST AFTER THE RESPONSE
 *
 * The bell is an insert on the same connection and cannot be half done. Email
 * and push are network calls to other people's services, so they run after the
 * page has been sent, one failure does not stop the next, and what happened is
 * written to the audit log.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class AnnouncementDelivery
{
    /** Recipients per message, the same batch the launch welcome uses. */
    protected const BATCH = 50;

    public function __construct(
        protected Notifier $notifier,
        protected WebPush $push,
        protected AuditLog $audit,
    ) {}

    /**
     * Deliver this post now if it is waiting and its start date has come.
     */
    public function deliverIfDue(Announcement $announcement, ?User $actor): void
    {
        if (! $announcement->delivery_pending || $announcement->isDraft() || $announcement->starts_on->isFuture()) {
            return;
        }

        $claimed = Announcement::whereKey($announcement->id)
            ->where('delivery_pending', true)
            ->update(['delivery_pending' => false]);

        if ($claimed === 0) {
            return;
        }

        // Already over by the time it was due: the moment to mention it has gone.
        if ($announcement->ends_on?->endOfDay()->isPast()) {
            return;
        }

        $staff = $this->staffFor($announcement)
            ->reject(fn (User $user) => $user->id === $actor?->id)
            ->values();

        $this->notifier->announcementPosted($announcement, $staff);

        if ($announcement->for_clients) {
            $this->notifier->announcementForClients($announcement, $this->clients());
        }

        $sendOutward = fn () => $this->sendOutward($announcement, $staff, $actor);

        // Nobody is waiting on a console run, and there is no response to send first.
        app()->runningInConsole() ? $sendOutward() : app()->terminating($sendOutward);
    }

    /**
     * Every scheduled post whose day has come. Returns how many were delivered.
     */
    public function deliverDue(): int
    {
        $due = Announcement::query()->with('audienceDepartment')->dueForDelivery()->get();

        foreach ($due as $announcement) {
            $this->deliverIfDue($announcement, null);
        }

        return $due->count();
    }

    /**
     * The fallback for a night the cron job did not run, from the page layout.
     *
     * Tolerates a database that has not been migrated yet, because the layout is
     * on every page and Admin > Updates, which applies migrations, is one of them.
     */
    public static function deliverDueQuietly(): void
    {
        try {
            if (Announcement::query()->dueForDelivery()->exists()) {
                app(self::class)->deliverDue();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The staff a post is addressed to, the same reading as Announcement::scopeVisibleTo.
     *
     * @return Collection<int, User>
     */
    public function staffFor(Announcement $announcement): Collection
    {
        return $this->staffQueryFor($announcement->audience, $announcement->audience_department_id)->get();
    }

    /**
     * How many people each choice of "Who sees it" reaches, for the compose form.
     *
     * @return array{everyone: int, managers: int, departments: array<int, int>}
     */
    public function audienceSizes(): array
    {
        $departments = Employee::query()
            ->whereNotNull('department_id')
            ->whereHas('user', fn ($q) => $q->where('status', 'active')->where('account_type', Realm::STAFF))
            ->selectRaw('department_id, count(*) as people')
            ->groupBy('department_id')
            ->pluck('people', 'department_id')
            ->map(fn ($people) => (int) $people)
            ->all();

        return [
            'everyone' => $this->staffQueryFor('everyone', null)->count(),
            'managers' => $this->staffQueryFor('managers', null)->count(),
            'departments' => $departments,
        ];
    }

    /**
     * @return Builder<User>
     */
    protected function staffQueryFor(string $audience, ?int $departmentId)
    {
        return User::query()
            ->where('status', 'active')
            ->where('account_type', Realm::STAFF)
            ->when($audience === 'department', fn ($q) => $q->whereIn(
                'id',
                Employee::query()->where('department_id', $departmentId)->select('user_id'),
            ))
            ->when($audience === 'managers', fn ($q) => $q->whereHas(
                'roles',
                fn ($roles) => $roles->where('role_key', 'manager'),
            ));
    }

    /**
     * @return Collection<int, User>
     */
    protected function clients(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->where('account_type', Realm::CLIENT)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $staff
     */
    protected function sendOutward(Announcement $announcement, Collection $staff, ?User $actor): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $emailed = $announcement->notify_email ? $this->email($announcement, $staff) : 0;
        $pushed = $this->push->sendTo($staff->pluck('id')->all());

        $this->audit->record(
            action: AuditLog::ANNOUNCEMENT_DELIVERED,
            actor: $actor,
            entityType: 'announcement',
            entityId: $announcement->reference,
            after: 'Bell: '.$staff->count().' staff'
                .($announcement->for_clients ? ', client portal' : '')
                .' · email: '.($announcement->notify_email ? $emailed.' sent' : 'not asked for')
                .' · push: '.$pushed.' devices',
        );
    }

    /**
     * BCC in batches, so one post is a few messages rather than one per person and
     * nobody sees who else it went to. Returns how many were actually handed over.
     *
     * @param  Collection<int, User>  $staff
     */
    protected function email(Announcement $announcement, Collection $staff): int
    {
        $addresses = $staff->pluck('email')->filter()->unique()->values();
        $sent = 0;

        foreach ($addresses->chunk(self::BATCH) as $batch) {
            try {
                Mail::to(config('mail.from.address'))
                    ->bcc($batch->all())
                    ->send(new AnnouncementMail($announcement));

                $sent += $batch->count();
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }
}
