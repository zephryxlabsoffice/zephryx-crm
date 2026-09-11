<?php

namespace App\Support\Admin;

use App\Models\Employee;
use App\Support\AttendanceDirectory;
use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter;
use App\Support\LeaveDirectory;
use App\Support\LeavePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a settings change would do to records that already exist.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE POINT: NAME THE DAMAGE BEFORE THE SAVE, NOT AFTER
 *
 * "half_day_hours: 4 → 6" is a true audit entry and a useless one. What
 * actually happened is that forty-seven days across eleven people stopped being
 * full days — retroactively, silently, in months that have already been
 * reported on.
 *
 * This class computes that second sentence. The settings form shows it on a
 * confirmation step before anything is written, and the audit entry records it
 * alongside the value, so the log says what the change DID rather than only
 * what it was.
 *
 * The two-step shape is borrowed from marking payroll paid, for the same
 * reason: an act that is hard to notice and awkward to undo gets a step that
 * names who it lands on.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS, AND WHY THAT IS ACCEPTABLE HERE
 *
 * The policies read config at call time, so this evaluates the history twice —
 * once as things stand, once with the proposed value pushed into config — and
 * diffs the two. Swapping config under a computation is not something to do
 * casually; it is contained to this class, always restored in a `finally`, and
 * never touches anything that writes.
 *
 * When settings become effective-dated (see the head of SettingsCatalogue) this
 * class survives as the thing that answers "what would this do", because the
 * question stays worth asking even once the past is safe.
 */
class Retroactive
{
    /** How far back to look. Bounded on purpose: this runs on a form submit. */
    protected const WINDOW_DAYS = 45;

    /**
     * @return array{affected: int, people: int, summary: string, changes: list<array<string, string>>}
     */
    public static function preview(string $key, mixed $proposed): array
    {
        return match (true) {
            str_starts_with($key, 'attendance.') => self::attendance($key, $proposed),
            str_starts_with($key, 'leave.types.') => self::leave($key, $proposed),
            default => self::nothing(),
        };
    }

    /**
     * @return array{affected: int, people: int, summary: string, changes: list<array<string, string>>}
     */
    protected static function nothing(): array
    {
        return [
            'affected' => 0,
            'people' => 0,
            'summary' => 'Nothing already recorded changes.',
            'changes' => [],
        ];
    }

    /**
     * Re-judge every day in the window under the proposed rule.
     *
     * @return array{affected: int, people: int, summary: string, changes: list<array<string, string>>}
     */
    protected static function attendance(string $key, mixed $proposed): array
    {
        $before = self::attendanceStates();

        $original = config($key);

        try {
            config([$key => $proposed]);
            $after = self::attendanceStates();
        } finally {
            // Always. A settings preview that leaked its proposed value into
            // the running configuration would change the very pages that are
            // about to render the warning.
            config([$key => $original]);
        }

        $moved = [];
        $people = [];

        foreach ($before as $id => $state) {
            if (($after[$id] ?? $state) === $state) {
                continue;
            }

            [$employee, $date] = explode('|', $id);

            $people[$employee] = true;
            $moved[$employee][] = ['date' => $date, 'from' => $state, 'to' => $after[$id]];
        }

        /*
         * Examples spread ACROSS people, one each before any second.
         *
         * Taken in natural order they came out as eight consecutive Saturdays
         * belonging to Riya Sharma, under a heading claiming eleven people were
         * affected — which reads as though the summary is wrong. The examples
         * are there to make the number believable, so they have to look like
         * the number.
         */
        $changes = [];

        for ($round = 0; count($changes) < 8; $round++) {
            $added = false;

            foreach ($moved as $employee => $entries) {
                if (! isset($entries[$round]) || count($changes) >= 8) {
                    continue;
                }

                $added = true;

                $changes[] = [
                    'who' => self::names()[$employee] ?? $employee,
                    'when' => AttendancePresenter::date($entries[$round]['date']),
                    'from' => AttendancePresenter::state($entries[$round]['from'])['label'],
                    'to' => AttendancePresenter::state($entries[$round]['to'])['label'],
                ];
            }

            if (! $added) {
                break;
            }
        }

        $affected = count(array_filter(
            $before,
            fn (string $state, string $id) => ($after[$id] ?? $state) !== $state,
            ARRAY_FILTER_USE_BOTH,
        ));

        return [
            'affected' => $affected,
            'people' => count($people),
            'summary' => $affected === 0
                ? 'Nothing already recorded changes.'
                : self::plural($affected, 'day').' across '.self::plural(count($people), 'person', 'people')
                    .' would be re-judged.',
            'changes' => $changes,
        ];
    }

    /**
     * Every day in the window, for everybody, as the policy currently reads it.
     *
     * Keyed "employee|date" so the two passes can be diffed directly.
     *
     * @return array<string, string>
     */
    protected static function attendanceStates(): array
    {
        $states = [];

        foreach (self::workforce() as $employee) {
            $id = (string) $employee->id;

            $records = AttendanceDirectory::forEmployee($employee, self::WINDOW_DAYS)->keyBy('date');
            $leaveDates = AttendanceDirectory::leaveDates($employee->id);

            for ($offset = 0; $offset < self::WINDOW_DAYS; $offset++) {
                $date = Carbon::today()->subDays($offset);
                $key = $date->toDateString();

                /*
                 * Days with NO record are evaluated too, and they have to be:
                 * changing the weekly off is precisely a change to days nobody
                 * checked in on. A diff over recorded days only would report
                 * zero for the setting most likely to surprise somebody.
                 */
                $states[$id.'|'.$key] = AttendancePolicy::evaluate(
                    $date,
                    $records->get($key),
                    in_array($key, $leaveDates, true),
                )['state'];
            }
        }

        return $states;
    }

    /**
     * Who a changed entitlement would put over their allowance.
     *
     * @return array{affected: int, people: int, summary: string, changes: list<array<string, string>>}
     */
    protected static function leave(string $key, mixed $proposed): array
    {
        // leave.types.casual.days → casual
        $type = explode('.', $key)[2] ?? null;

        if ($type === null) {
            return self::nothing();
        }

        $proposed = (int) $proposed;
        $changes = [];
        $over = 0;

        foreach (self::workforce() as $employee) {
            $balance = LeavePolicy::balance(LeaveDirectory::forEmployee($employee));

            $row = collect($balance['types'])->firstWhere('key', $type);

            if ($row === null || $row['taken'] <= $proposed) {
                continue;
            }

            $over++;

            if (count($changes) < 8) {
                $changes[] = [
                    'who' => $employee->user?->name ?? $employee->id,
                    'when' => 'This year',
                    'from' => $row['taken'].' of '.$row['entitlement'].' taken',
                    'to' => $row['taken'].' of '.$proposed.' — over by '.($row['taken'] - $proposed),
                ];
            }
        }

        return [
            'affected' => $over,
            'people' => $over,
            'summary' => $over === 0
                ? 'Nobody has taken more than the new allowance.'
                : self::plural($over, 'person', 'people').' would be over the new allowance.',
            'changes' => $changes,
        ];
    }

    /**
     * Everybody a settings change could land on.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ACTIVE ACCOUNTS ONLY, AND THAT IS A JUDGEMENT WORTH STATING
     *
     * A closed record's attendance would be re-judged by the same rule change,
     * and its history is genuinely rewritten too. It is left out because the
     * confirmation is a warning about consequences somebody has to act on, and
     * "3 days for a person who left in March" is noise in front of the number
     * that matters.
     *
     * The rule still applies to them. This is a preview, not the change.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return Collection<int, Employee>
     */
    protected static function workforce(): Collection
    {
        return Employee::query()->with('user')->active()->get();
    }

    /**
     * Employee id to name, resolved once.
     *
     * The example rows name people, and looking each one up inside the loop
     * would be a query per example on a form submit.
     *
     * @return array<string, string>
     */
    protected static function names(): array
    {
        return self::workforce()
            ->mapWithKeys(fn (Employee $e) => [(string) $e->id => (string) $e->user?->name])
            ->all();
    }

    protected static function plural(int $n, string $singular, ?string $plural = null): string
    {
        return $n.' '.($n === 1 ? $singular : ($plural ?? $singular.'s'));
    }
}
