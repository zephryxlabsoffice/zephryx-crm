<?php

namespace App\Support\Admin;

use App\Support\AttendancePolicy;
use App\Support\LeavePolicy;

/**
 * Every setting the Admin Panel may change, and what changing it does.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * SOME OF THESE SETTINGS REWRITE THE PAST
 *
 * This is the thing a company-settings screen has to get right, and it is only
 * visible once the modules underneath exist.
 *
 * Attendance and Leave do not STORE their judgements. Whether a day was a half
 * day, whether it stopped counting, whether a Saturday was a working day, how
 * many leave days somebody has left — all of it is derived on read, from these
 * values, every time a page is opened. AttendancePolicy::monthSummary and
 * LeavePolicy::balance compute it live.
 *
 * So lowering `half_day_hours` from 4 to 6 does not change what happens from
 * tomorrow. It silently reclassifies months of days that have already been
 * worked, reported on and — once payroll is real — paid against. Nobody is
 * notified. Nothing in the record shows it moved. The audit entry, written
 * naively, would read "half_day_hours: 4 → 6", which is true and useless: the
 * thing that actually happened is "47 days across 11 people became half days".
 *
 * Every entry below therefore declares whether it is `retroactive`, and the
 * ones that are get their effect computed and shown BEFORE the save, by
 * App\Support\Admin\Retroactive. The confirmation names the damage.
 *
 * THE REAL FIX, WHEN IT IS WORTH IT: effective-dated settings — each value
 * stored with a from-date, and the policies reading the value in force on the
 * date being judged, so the past cannot move at all. That is a data-model
 * change touching every call site in AttendancePolicy and LeavePolicy, and it
 * was deferred deliberately (2026-09-07). This catalogue is what makes the
 * problem visible in the meantime; it does not solve it.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * The values are read from config today and move to the `company_settings`
 * table (§8) with the backend. The shape here matches: a flat key, a scalar
 * value, and a record of who changed it.
 */
class SettingsCatalogue
{
    /**
     * @return array<string, array{label: string, note: string, retroactive: bool, settings: list<array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            'company' => [
                'label' => 'Company',
                'note' => 'Names and addresses that appear on pages and in email.',
                'retroactive' => false,
                'settings' => self::company(),
            ],

            'working-day' => [
                'label' => 'The working day',
                'note' => 'What counts as a day worked. These rules are applied to every attendance '
                    .'record every time it is read, including ones already in the past.',
                'retroactive' => true,
                'settings' => self::workingDay(),
            ],

            'leave' => [
                'label' => 'Leave',
                'note' => 'Annual entitlements. Balances are calculated from these, so a change '
                    .'moves everyone\'s remaining days at once.',
                'retroactive' => true,
                'settings' => self::leave(),
            ],

            'meetings' => [
                'label' => 'Meetings',
                'note' => 'Defaults for meetings created from this application.',
                'retroactive' => false,
                'settings' => self::meetings(),
            ],

            'announcements' => [
                'label' => 'Announcements',
                'note' => 'What the board shows, and how far ahead milestones appear.',
                'retroactive' => false,
                'settings' => self::announcements(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function company(): array
    {
        return [
            self::setting('zephryx.brand.name', 'Company name', 'text', config('zephryx.brand.name'),
                'Shown in the sidebar, on invoices and in email subjects.'),

            self::setting('zephryx.support.email', 'Support mailbox', 'email', config('zephryx.support.email'),
                'Where "Contact Support" writes to, on every realm.'),

            self::setting('zephryx.currency.code', 'Currency', 'text', config('zephryx.currency.code'),
                'The default for new invoices. Existing invoices keep the currency they were '
                .'issued in — an amount cannot change currency after the fact.'),

            self::setting('zephryx.theme.default', 'Default theme', 'select', config('zephryx.theme.default'),
                'What somebody sees before they choose for themselves.',
                options: ['dark' => 'Dark', 'light' => 'Light']),
        ];
    }

    /**
     * The retroactive ones, and the reason this class exists.
     *
     * @return list<array<string, mixed>>
     */
    protected static function workingDay(): array
    {
        return [
            self::setting('attendance.work_start', 'Day starts', 'time', AttendancePolicy::workStart(),
                'Shown on the attendance pages. Nobody is marked late — see the Attendance module.'),

            self::setting('attendance.work_end', 'Day ends', 'time', AttendancePolicy::workEnd(),
                'Shown on the attendance pages.'),

            self::setting('attendance.half_day_hours', 'Half day under', 'hours', AttendancePolicy::halfDayHours(),
                'A day shorter than this counts as a half day.',
                retroactive: true,
                affects: 'Every attendance record ever made is re-judged against this number.'),

            self::setting('attendance.auto_reject_after_hours', 'Stop counting after', 'hours', AttendancePolicy::autoRejectAfterHours(),
                'A day left open longer than this stops counting. There is no way to fill in a '
                .'check-out later, which is why the number matters.',
                retroactive: true,
                affects: 'Days already left open are re-judged, in both directions.'),

            self::setting('attendance.week_off', 'Weekly off', 'days', AttendancePolicy::weekOff(),
                'Days the office is closed. Nobody is marked absent on them.',
                retroactive: true,
                affects: 'Adding a day turns every past one into a week-off; removing one turns '
                    .'every past one into a working day people may not have attended.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function leave(): array
    {
        $settings = [];

        foreach (LeavePolicy::allowanced() as $key => $type) {
            $settings[] = self::setting(
                'leave.types.'.$key.'.days',
                $type['label'],
                'number',
                $type['days'],
                'Days a year.',
                retroactive: true,
                affects: 'Balances are calculated live, so lowering this can put people '
                    .'retroactively over their entitlement.',
            );
        }

        $settings[] = self::setting('leave.carry_forward', 'Carry forward', 'number', LeavePolicy::carryForward(),
            'Unused days that survive into next year. Blank means none.');

        return $settings;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function meetings(): array
    {
        return [
            self::setting('meetings.default_duration', 'Default length', 'number', config('meetings.default_duration'),
                'Minutes, pre-filled when scheduling.'),

            self::setting('meetings.allow_external_guests', 'Allow external guests', 'toggle', config('meetings.allow_external_guests'),
                'Whether meetings may include addresses outside the company — clients, for instance.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function announcements(): array
    {
        return [
            self::setting('announcements.milestones.window_days', 'Milestones appear', 'number', config('announcements.milestones.window_days'),
                'Days ahead of a birthday or work anniversary.'),

            self::setting('announcements.milestones.birthdays', 'Announce birthdays', 'toggle', config('announcements.milestones.birthdays'),
                'Anyone may still opt out of their own.'),

            self::setting('announcements.milestones.anniversaries', 'Announce work anniversaries', 'toggle', config('announcements.milestones.anniversaries'),
                'Anyone may still opt out of their own.'),
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    protected static function setting(
        string $key,
        string $label,
        string $type,
        mixed $value,
        string $note,
        bool $retroactive = false,
        string $affects = '',
        array $options = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'value' => $value,
            'note' => $note,
            'retroactive' => $retroactive,
            'affects' => $affects,
            'options' => $options,
        ];
    }

    /**
     * The keys whose change rewrites records that already exist.
     *
     * @return list<string>
     */
    public static function retroactiveKeys(): array
    {
        $keys = [];

        foreach (self::groups() as $group) {
            foreach ($group['settings'] as $setting) {
                if ($setting['retroactive']) {
                    $keys[] = $setting['key'];
                }
            }
        }

        return $keys;
    }

    public static function isRetroactive(string $key): bool
    {
        return in_array($key, self::retroactiveKeys(), true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::groups() as $group) {
            foreach ($group['settings'] as $setting) {
                if ($setting['key'] === $key) {
                    return $setting;
                }
            }
        }

        return null;
    }
}
