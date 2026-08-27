<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns an invoice row into the things its pages need to draw it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * STATUS IS DERIVED, NOT STORED
 *
 * An invoice does not have a status column that somebody sets. It has a total,
 * a set of payments, a due date, and a cancelled flag — and the status falls
 * out of those. Nobody can mark an unpaid invoice "Paid", because there is no
 * field to mark; the way to make an invoice paid is to record the payment that
 * makes it paid.
 *
 * The one exception is `cancelled`, which is a real flag, because "we withdrew
 * this" is not something any combination of payments can express.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InvoicePresenter
{
    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const PARTIAL = 'partial';
    public const PAID = 'paid';
    public const OVERDUE = 'overdue';
    public const CANCELLED = 'cancelled';

    /** @var array<string, array{0: string, 1: string, 2: string}> tone, label, meaning */
    protected const STATUSES = [
        self::DRAFT => ['pill-gray', 'Draft', 'Not sent to the client yet'],
        self::SENT => ['pill-blue', 'Sent', 'Awaiting payment'],
        self::PARTIAL => ['pill-amber', 'Partly paid', 'Some of it has been received'],
        self::PAID => ['pill-green', 'Paid', 'Settled in full'],
        self::OVERDUE => ['pill-red', 'Overdue', 'Past its due date and unpaid'],
        self::CANCELLED => ['pill-gray', 'Cancelled', 'Withdrawn — never delete an invoice'],
    ];

    /**
     * Work out where an invoice stands.
     *
     * Order matters. Cancelled outranks everything, because a cancelled invoice
     * that happens to be past its due date is not "overdue" — nobody owes it.
     * Paid outranks overdue for the same reason: money that arrived late is
     * history, not an outstanding debt. This mirrors the rule Projects and
     * Tasks already use for late-but-finished work.
     *
     * @param  array<string, mixed>  $invoice
     */
    public static function statusOf(array $invoice): string
    {
        if ($invoice['cancelled'] ?? false) {
            return self::CANCELLED;
        }

        $total = $invoice['total'];
        $paid = $invoice['paid'];

        if ($total->isPositive() && $paid->greaterThanOrEqual($total)) {
            return self::PAID;
        }

        if (! ($invoice['issued'] ?? true)) {
            return self::DRAFT;
        }

        // Past due beats partly-paid: a half-paid invoice three weeks late is
        // a collection problem, and calling it "Partly paid" buries that.
        if (self::isPastDue($invoice)) {
            return self::OVERDUE;
        }

        if ($paid->isPositive()) {
            return self::PARTIAL;
        }

        return self::SENT;
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    public static function isPastDue(array $invoice): bool
    {
        return Carbon::parse($invoice['due_date'])->endOfDay()->isPast();
    }

    /**
     * Days until due — negative once it has passed.
     *
     * @param  array<string, mixed>  $invoice
     */
    public static function daysUntilDue(array $invoice): int
    {
        return (int) Carbon::today()->diffInDays(Carbon::parse($invoice['due_date'])->startOfDay(), false);
    }

    /**
     * How the due date should read, and whether it is worth colouring.
     *
     * Only a problem gets a colour. Colouring every date red the moment it
     * passes — which the handover did — means the colour stops meaning
     * anything, and a settled invoice from March is not a problem.
     *
     * @param  array<string, mixed>  $invoice
     * @return array{label: string, tone: string}
     */
    public static function dueState(array $invoice): array
    {
        $status = self::statusOf($invoice);

        if ($status === self::PAID) {
            return ['label' => 'Settled', 'tone' => 'is-done'];
        }

        if ($status === self::CANCELLED) {
            return ['label' => 'Cancelled', 'tone' => ''];
        }

        if ($status === self::DRAFT) {
            return ['label' => 'Not sent', 'tone' => ''];
        }

        $days = self::daysUntilDue($invoice);

        if ($days < 0) {
            $late = abs($days);

            return [
                'label' => $late === 1 ? '1 day overdue' : "{$late} days overdue",
                'tone' => 'is-overdue',
            ];
        }

        if ($days === 0) {
            return ['label' => 'Due today', 'tone' => 'is-soon'];
        }

        if ($days <= 7) {
            return ['label' => $days === 1 ? 'Due tomorrow' : "Due in {$days} days", 'tone' => 'is-soon'];
        }

        return ['label' => "Due in {$days} days", 'tone' => ''];
    }

    /**
     * @return array{tone: string, label: string, meaning: string}
     */
    public static function status(string $status): array
    {
        [$tone, $label, $meaning] = self::STATUSES[$status]
            ?? ['pill-gray', ucfirst(str_replace('_', ' ', $status)), ''];

        return ['tone' => $tone, 'label' => $label, 'meaning' => $meaning];
    }

    /**
     * @return list<string>
     */
    public static function statusOptions(): array
    {
        return array_keys(self::STATUSES);
    }

    /**
     * The statuses that mean somebody still owes us money.
     *
     * @return list<string>
     */
    public static function outstandingStatuses(): array
    {
        return [self::SENT, self::PARTIAL, self::OVERDUE];
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    public static function isOutstanding(array $invoice): bool
    {
        return in_array(self::statusOf($invoice), self::outstandingStatuses(), true);
    }

    public static function date(Carbon|string $when): string
    {
        return Carbon::parse($when)->format('d M Y');
    }

    /**
     * The payment terms an invoice was written on, stated in words.
     *
     * Derived from the two dates rather than stored, so it can never contradict
     * them — "Net 30" beside dates 45 days apart is the kind of detail a client
     * notices and queries.
     *
     * @param  array<string, mixed>  $invoice
     */
    public static function terms(array $invoice): string
    {
        $days = (int) Carbon::parse($invoice['invoice_date'])
            ->startOfDay()
            ->diffInDays(Carbon::parse($invoice['due_date'])->startOfDay(), false);

        return $days <= 0 ? 'Due on receipt' : "Net {$days}";
    }
}
