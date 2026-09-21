<?php

namespace Tests\Unit;

use App\Support\LeavePolicy;
use App\Support\LeavePresenter as P;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeavePolicyTest extends TestCase
{
    /**
     * @param  array<int, array{type: string, status: string, days: int|float}>  $rows
     * @return list<array<string, mixed>>
     */
    protected function requests(array $rows): array
    {
        return $rows;
    }

    /* ────────────  the policy is read, not owned  ──────────── */

    public function test_the_policy_comes_from_configuration(): void
    {
        // Leave types and entitlements belong to the Admin Panel (§12). When
        // Settings ships, only `types()` changes — everything else in the
        // module already goes through this class.
        config(['leave.types' => [
            'annual' => ['label' => 'Annual Leave', 'days' => 20, 'tone' => 'lv-casual', 'note' => ''],
        ]]);

        $this->assertSame(['annual'], LeavePolicy::typeKeys());
        $this->assertSame('Annual Leave', LeavePolicy::label('annual'));
        $this->assertSame(20, LeavePolicy::totalEntitlement());
    }

    public function test_a_type_removed_from_the_policy_still_renders(): void
    {
        // A leave record whose type was deleted is a data problem. Hiding it
        // makes it an invisible one.
        config(['leave.types' => []]);

        $type = LeavePolicy::type('sabbatical');

        $this->assertSame('Sabbatical Leave', $type['label']);
        $this->assertNull($type['days']);
        $this->assertStringContainsString('No longer in the leave policy', $type['note']);
    }

    /* ────────────  no allowance is not a zero allowance  ──────────── */

    public function test_unpaid_leave_has_no_balance_and_is_not_counted_as_entitlement(): void
    {
        // The handover gave unpaid leave a two-day balance on the same screen
        // where its own policy card said "As Per Policy".
        $this->assertNull(LeavePolicy::entitlementFor('unpaid'));
        $this->assertArrayNotHasKey('unpaid', LeavePolicy::allowanced());

        $balance = LeavePolicy::balance([]);

        foreach ($balance['types'] as $row) {
            $this->assertNotSame('unpaid', $row['key']);
        }
    }

    public function test_unpaid_days_are_reported_separately_never_netted_off(): void
    {
        $balance = LeavePolicy::balance([
            ['type' => 'unpaid', 'status' => P::APPROVED, 'days' => 5],
            ['type' => 'casual', 'status' => P::APPROVED, 'days' => 2],
        ]);

        $this->assertSame(5, $balance['unpaid']);
        // The unpaid days do not touch the allowance totals.
        $this->assertSame(2, $balance['taken']);
        $this->assertSame(LeavePolicy::totalEntitlement() - 2, $balance['remaining']);
    }

    /* ────────────  the balance arithmetic  ──────────── */

    public function test_only_approved_days_come_off_a_balance(): void
    {
        // A pending request has not been granted; a rejected or withdrawn one
        // never was.
        $balance = LeavePolicy::balance([
            ['type' => 'casual', 'status' => P::APPROVED, 'days' => 3],
            ['type' => 'casual', 'status' => P::PENDING, 'days' => 2],
            ['type' => 'casual', 'status' => P::REJECTED, 'days' => 5],
            ['type' => 'casual', 'status' => P::CANCELLED, 'days' => 4],
        ]);

        $casual = collect($balance['types'])->firstWhere('key', 'casual');

        $this->assertSame(3, $casual['taken']);
        $this->assertSame(2, $casual['pending']);
        $this->assertSame($casual['entitlement'] - 3, $casual['remaining']);
    }

    public function test_pending_days_are_reported_but_not_deducted(): void
    {
        // Showing a balance that already assumes approval is how somebody plans
        // around days they may not get.
        $balance = LeavePolicy::balance([
            ['type' => 'privilege', 'status' => P::PENDING, 'days' => 5],
        ]);

        $this->assertSame(5, $balance['pending']);
        $this->assertSame(0, $balance['taken']);
        $this->assertSame(LeavePolicy::totalEntitlement(), $balance['remaining']);
    }

    public function test_the_totals_are_the_sum_of_the_type_rows(): void
    {
        // The handover's figures did not reconcile: 34 days granted, 12 taken,
        // and every balance tile reading 18 rather than 22.
        $balance = LeavePolicy::balance([
            ['type' => 'casual', 'status' => P::APPROVED, 'days' => 3],
            ['type' => 'sick', 'status' => P::APPROVED, 'days' => 1],
            ['type' => 'privilege', 'status' => P::APPROVED, 'days' => 3],
        ]);

        $this->assertSame(array_sum(array_column($balance['types'], 'entitlement')), $balance['entitlement']);
        $this->assertSame(array_sum(array_column($balance['types'], 'taken')), $balance['taken']);
        $this->assertSame(array_sum(array_column($balance['types'], 'remaining')), $balance['remaining']);
        $this->assertSame($balance['entitlement'] - $balance['taken'], $balance['remaining']);
    }

    public function test_an_overdrawn_balance_clamps_at_zero(): void
    {
        // Somebody granted more than their allowance is a conversation, not a
        // negative number on a dashboard.
        $balance = LeavePolicy::balance([
            ['type' => 'casual', 'status' => P::APPROVED, 'days' => 99],
        ]);

        $casual = collect($balance['types'])->firstWhere('key', 'casual');

        $this->assertSame(0, $casual['remaining']);
        $this->assertGreaterThanOrEqual(0, $balance['remaining']);
    }

    public function test_an_empty_history_leaves_the_full_entitlement(): void
    {
        $balance = LeavePolicy::balance([]);

        $this->assertSame(LeavePolicy::totalEntitlement(), $balance['remaining']);
        $this->assertSame(0, $balance['taken']);
        $this->assertSame(0, $balance['pending']);
        $this->assertSame(0, $balance['unpaid']);
    }

    /* ────────────  the per-employee leave year (decided 2026-09-11)  ──────────── */

    public function test_the_leave_year_starts_on_the_joining_anniversary(): void
    {
        $joined = Carbon::parse('2024-03-15');

        // Before this year's anniversary: still last year's window.
        $this->assertSame(
            '2025-03-15',
            LeavePolicy::leaveYearStart($joined, Carbon::parse('2026-03-10'))->toDateString(),
        );

        // On or after it: this year's.
        $this->assertSame(
            '2026-03-15',
            LeavePolicy::leaveYearStart($joined, Carbon::parse('2026-03-15'))->toDateString(),
        );
    }

    public function test_a_february_29th_joiner_clamps_rather_than_overflows(): void
    {
        // Naive date arithmetic turns "2026-02-29" into "2026-03-01". That is
        // a different day, not the closest one to what was actually agreed.
        $joined = Carbon::parse('2024-02-29');

        $this->assertSame(
            '2026-02-28',
            LeavePolicy::leaveYearStart($joined, Carbon::parse('2026-06-01'))->toDateString(),
        );
    }

    public function test_months_accrued_is_zero_until_a_full_month_has_passed(): void
    {
        $start = Carbon::parse('2026-01-01');

        $this->assertSame(0, LeavePolicy::monthsAccrued($start, Carbon::parse('2026-01-15')));
        $this->assertSame(1, LeavePolicy::monthsAccrued($start, Carbon::parse('2026-02-01')));
        $this->assertSame(6, LeavePolicy::monthsAccrued($start, Carbon::parse('2026-07-01')));
        // Capped, not left to run past a full year.
        $this->assertSame(12, LeavePolicy::monthsAccrued($start, Carbon::parse('2030-01-01')));
    }

    public function test_casual_accrues_monthly_privilege_and_sick_are_granted_in_full(): void
    {
        $start = Carbon::parse('2026-01-01');
        $asOf = Carbon::parse('2026-04-01'); // 3 months in

        $this->assertSame(3, LeavePolicy::accruedEntitlement('casual', $start, $asOf));
        $this->assertSame(LeavePolicy::entitlementFor('sick'), LeavePolicy::accruedEntitlement('sick', $start, $asOf));
        $this->assertSame(LeavePolicy::entitlementFor('privilege'), LeavePolicy::accruedEntitlement('privilege', $start, $asOf));
        // Unpaid has no entitlement to accrue.
        $this->assertSame(0, LeavePolicy::accruedEntitlement('unpaid', $start, $asOf));
    }

    public function test_balance_with_a_leave_year_uses_accrued_entitlement(): void
    {
        $start = Carbon::parse('2026-01-01');
        $asOf = Carbon::parse('2026-04-01');

        $balance = LeavePolicy::balance([
            ['type' => 'casual', 'status' => P::APPROVED, 'days' => 2, 'from' => '2026-02-01'],
        ], $start, $asOf);

        $casual = collect($balance['types'])->firstWhere('key', 'casual');

        // 3 accrued, 2 taken, 1 left — not the flat annual figure.
        $this->assertSame(3, $casual['entitlement']);
        $this->assertSame(12, $casual['annual_entitlement']);
        $this->assertSame(1, $casual['remaining']);
    }

    public function test_balance_without_a_leave_year_still_uses_the_flat_annual_figure(): void
    {
        // Backward compatible: every existing test above calls balance() with
        // no calendar at all and still gets the old, simple arithmetic.
        $balance = LeavePolicy::balance([
            ['type' => 'casual', 'status' => P::APPROVED, 'days' => 2],
        ]);

        $casual = collect($balance['types'])->firstWhere('key', 'casual');

        $this->assertSame(12, $casual['entitlement']);
    }

    public function test_a_request_from_a_lapsed_leave_year_does_not_eat_into_the_current_one(): void
    {
        // Unused days lapse at the end of an employee's leave year (decided).
        // A request dated before the current year started must not count
        // against a balance that has since reset.
        $start = Carbon::parse('2026-01-01');

        $balance = LeavePolicy::balance([
            ['type' => 'casual', 'status' => P::APPROVED, 'days' => 5, 'from' => '2025-06-01'],
        ], $start, Carbon::parse('2026-04-01'));

        $casual = collect($balance['types'])->firstWhere('key', 'casual');

        $this->assertSame(0, $casual['taken']);
    }

    /* ────────────  what is deliberately absent  ──────────── */

    public function test_the_policy_does_not_calculate_working_days(): void
    {
        // Decided 2026-08-28: this module counts what was recorded rather than
        // working out what should have been. If any of these appear, the
        // question is which rule they encode and who agreed it.
        $this->assertFalse(method_exists(LeavePolicy::class, 'workingDays'));
        $this->assertFalse(method_exists(LeavePolicy::class, 'holidays'));
        $this->assertFalse(method_exists(LeavePolicy::class, 'deduct'));
    }

    public function test_carry_forward_is_undecided_and_says_so(): void
    {
        $this->assertNull(LeavePolicy::carryForward());
    }
}
