<?php

namespace App\Support;

/**
 * The company's leave policy, and the arithmetic over it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE POLICY IS NOT OWNED HERE
 *
 * Leave types and their annual entitlements are company policy, configured in
 * the Admin Panel (§12). This class reads them; it does not define them. Today
 * they come from `config/leave.php`; when Settings ships they come from a
 * table, and only `types()` changes.
 *
 * Every page goes through this class rather than reaching for the config
 * directly, which is what makes that swap one method rather than a search.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS MODULE COUNTS. IT DOES NOT DECIDE.
 *
 * Decided 2026-08-28: leave is a portal for requesting and counting, not a
 * rules engine. So there is deliberately no working-day calculator here, no
 * holiday calendar and no automatic deduction on approval.
 *
 * The number of days a request costs is stated by the person making it and
 * agreed by the person approving it. Whether a Saturday counts, whether a
 * public holiday in the middle of a week is skipped, whether half a day is
 * possible — those are judgements the two of them make, and encoding a guess
 * at them would produce balances that quietly disagree with what people were
 * actually granted.
 *
 * What IS computed is the sum of what has been recorded: entitlement, less the
 * days on approved requests. That is counting, and it cannot be wrong unless
 * the records are.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LeavePolicy
{
    /**
     * @return array<string, array{label: string, days: int|null, tone: string, note: string}>
     */
    public static function types(): array
    {
        return config('leave.types', []);
    }

    /**
     * @return list<string>
     */
    public static function typeKeys(): array
    {
        return array_keys(self::types());
    }

    /**
     * @return array{key: string, label: string, days: int|null, tone: string, note: string}
     */
    public static function type(string $key): array
    {
        $type = self::types()[$key] ?? [
            // An unrecognised type still has to appear. A leave record whose
            // type was removed from the policy is a data problem, and hiding it
            // makes it an invisible one.
            'label' => ucfirst(str_replace('_', ' ', $key)).' Leave',
            'days' => null,
            'tone' => 'lv-unpaid',
            'note' => 'No longer in the leave policy.',
        ];

        return ['key' => $key] + $type;
    }

    public static function label(string $key): string
    {
        return self::type($key)['label'];
    }

    /**
     * The types that carry an annual allowance.
     *
     * Unpaid leave is excluded because it has none — showing a "balance" for it
     * would be inventing an allowance nobody has. The handover's donut gave it
     * two days on the same screen where its own policy card said "As Per
     * Policy".
     *
     * @return array<string, array{label: string, days: int|null, tone: string, note: string}>
     */
    public static function allowanced(): array
    {
        return array_filter(self::types(), fn (array $type) => $type['days'] !== null);
    }

    /**
     * Total days granted per year across every type that has an allowance.
     */
    public static function totalEntitlement(): int
    {
        return array_sum(array_column(self::allowanced(), 'days'));
    }

    public static function entitlementFor(string $key): ?int
    {
        return self::type($key)['days'];
    }

    /**
     * A person's balance, per type and in total.
     *
     * `$requests` is that person's leave requests. Only APPROVED ones count
     * against a balance: a pending request has not been granted, and a rejected
     * or cancelled one never was. Pending days are reported separately so
     * somebody can see what would happen if everything outstanding were
     * approved — which is the number an approver actually needs.
     *
     * @param  iterable<int, array<string, mixed>>  $requests
     * @return array{
     *     types: list<array{key: string, label: string, tone: string, entitlement: int, taken: int, pending: int, remaining: int}>,
     *     entitlement: int, taken: int, pending: int, remaining: int, unpaid: int
     * }
     */
    public static function balance(iterable $requests): array
    {
        $rows = collect($requests);
        $types = [];
        $totals = ['entitlement' => 0, 'taken' => 0, 'pending' => 0, 'remaining' => 0];

        foreach (self::allowanced() as $key => $type) {
            $forType = $rows->where('type', $key);

            $taken = (int) $forType->where('status', LeavePresenter::APPROVED)->sum('days');
            $pending = (int) $forType->where('status', LeavePresenter::PENDING)->sum('days');

            // Clamped at zero: somebody granted more than their allowance is a
            // conversation, not a negative number on a dashboard.
            $remaining = max(0, $type['days'] - $taken);

            $types[] = [
                'key' => $key,
                'label' => $type['label'],
                'tone' => $type['tone'],
                'entitlement' => $type['days'],
                'taken' => $taken,
                'pending' => $pending,
                'remaining' => $remaining,
            ];

            $totals['entitlement'] += $type['days'];
            $totals['taken'] += $taken;
            $totals['pending'] += $pending;
            $totals['remaining'] += $remaining;
        }

        return $totals + [
            'types' => $types,
            // Unpaid days are reported, never netted off a balance they do not
            // belong to.
            'unpaid' => (int) $rows
                ->where('status', LeavePresenter::APPROVED)
                ->filter(fn (array $r) => self::entitlementFor($r['type']) === null)
                ->sum('days'),
        ];
    }

    /**
     * Whether unused days carry into next year.
     *
     * Undecided as of 2026-08-28, which is why the pages say the balance is for
     * this year rather than showing an "expired" figure whose rule does not
     * exist. The handover showed one, reading zero.
     */
    public static function carryForward(): ?int
    {
        return config('leave.carry_forward');
    }
}
