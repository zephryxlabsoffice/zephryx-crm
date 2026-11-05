<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Notification;

/**
 * The evening nudge for anybody who has not filed today. Run by the cron job
 * `eod:remind`, see docs/DEPLOY.md.
 */
class EodReminders
{
    public const KIND = 'eod_reminder';

    public function __construct(protected Notifier $notifier, protected WebPush $push) {}

    /**
     * Returns how many people were reminded. Running it twice in a day reminds
     * nobody twice, so a cron job set up twice by mistake is harmless.
     */
    public function send(): int
    {
        if (! EodPolicy::isLive()) {
            return 0;
        }

        $alreadyReminded = Notification::query()
            ->where('kind', self::KIND)
            ->whereDate('created_at', now()->toDateString())
            ->pluck('user_id');

        $people = EodPolicy::notFiledOn(now()->toDateString())
            ->with('user')
            ->whereNotIn('user_id', $alreadyReminded)
            ->get();

        foreach ($people as $employee) {
            /** @var Employee $employee */
            $this->notifier->eodReminder($employee->user);
        }

        $this->push->sendTo($people->pluck('user_id')->all());

        return $people->count();
    }
}
