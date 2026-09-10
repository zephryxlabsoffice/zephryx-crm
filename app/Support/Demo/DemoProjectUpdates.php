<?php

namespace App\Support\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Daily project updates — the end-of-day notes staff write against a project.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * EVERY UPDATE HAS A VISIBILITY, AND THE DEFAULT IS INTERNAL (decided 2026-09-07)
 *
 * These are written by employees, at the end of a day, quickly. That is exactly
 * why they are useful, and exactly why they cannot all be published to the
 * client: real EOD notes say things like "blocked until they pay the March
 * invoice" and "redoing this because the brief changed again". Every one of
 * those sentences is true, worth recording, and disastrous to put in front of
 * the client it is about.
 *
 * The alternative considered and rejected was making all updates client-visible
 * and asking people to write accordingly. That does not remove the internal
 * note — it removes the PLACE for one, so it ends up in a direct message where
 * nobody can find it later, or the update stops being written at all.
 *
 * So: one field, `visibility`, and it defaults to internal. The direction of
 * that default is the whole safety argument. Forgetting to set it hides an
 * update that should have been shared — an annoyance, fixed by a click. The
 * opposite default would mean forgetting publishes something, which is not
 * fixable at all once it has been read.
 *
 * There is deliberately NO `all()` returning both kinds to a caller who then
 * filters. The client portal calls clientVisibleFor() and cannot reach the
 * others; see the head of ClientPortal for why that shape rather than a
 * check in the view.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * WHAT THE BACKEND OWES
 *
 * 1. THE COLUMN DEFAULTS TO INTERNAL at the database level, not in a form. A
 *    row inserted by a script, a seeder or a future import must be internal
 *    unless it says otherwise.
 *
 * 2. PUBLISHING IS ITS OWN ACT, and audited (§6). Who made an update visible to
 *    a client, and when, is the question somebody asks afterwards.
 *
 * 3. CHANGING VISIBILITY TO INTERNAL DOES NOT UNPUBLISH IT. If the client has
 *    read it, it has been read. Hiding it afterwards is housekeeping, and the
 *    interface must not imply it is a recall.
 */
class DemoProjectUpdates
{
    public const INTERNAL = 'internal';
    public const CLIENT = 'client';

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * Updates for one project, newest first, INTERNAL ONES INCLUDED.
     *
     * Protected, not public. This is the staff view of the log and the client
     * portal must not be able to reach it — the only public reader below is the
     * one that filters. When the staff-side EOD page is built it becomes a
     * public method on a staff-facing source, guarded by its own route.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected static function forProject(string $project): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return collect(self::rows())
            ->where('project', $project)
            ->map(function (array $row) {
                $employees = DemoEmployees::all()->keyBy('user_id');

                return $row + [
                    'author_record' => $employees->get($row['author']),
                    'posted_at' => Carbon::today()->addDays($row['day'])->setTime($row['at'][0], $row['at'][1]),
                ];
            })
            ->sortByDesc('posted_at')
            ->values();
    }

    /**
     * The updates on a project that a client may read.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function clientVisibleFor(string $project): Collection
    {
        return self::forProject($project)
            ->where('visibility', self::CLIENT)
            ->values();
    }

    /**
     * How many updates on a project are internal.
     *
     * For the staff side, so somebody writing an update can see the split. The
     * client portal never asks — a count of what is being withheld is itself a
     * disclosure.
     */
    public static function internalCountFor(string $project): int
    {
        return self::forProject($project)->where('visibility', self::INTERNAL)->count();
    }

    /**
     * The fixture rows, for the seeder that turns them into real ones.
     *
     * Public where rows() is protected, and gated like everything else here:
     * the seeder needs the whole list, internal notes included, and gets it
     * only where a Demo class is allowed to answer at all.
     *
     * @return list<array<string, mixed>>
     */
    public static function seedRows(): array
    {
        return self::enabled() ? self::rows() : [];
    }

    /**
     * `day` is an offset from today and `at` is [hour, minute], so the log
     * stays plausibly recent instead of drifting into last year the way the
     * handover's fixed 2024 dates did.
     *
     * The internal rows below are not filler. They are the shape of note this
     * module exists to keep out of the client's view — read them, and the
     * default makes its own argument.
     *
     * @return list<array<string, mixed>>
     */
    protected static function rows(): array
    {
        return [
            // ── Website Redesign (DGL International School) ──────────────────
            [
                'id' => 'UPD-2026-061', 'project' => 'WD-2024-001', 'author' => 'EMP001',
                'day' => 0, 'at' => [18, 10], 'visibility' => self::CLIENT,
                'title' => 'Homepage layout signed off internally',
                'body' => 'The revised homepage is through internal review and ready for your look. '
                    .'Next up is the admissions page, which we expect to have with you this week.',
                'attachments' => [
                    ['name' => 'Homepage_v4.pdf', 'kind' => 'PDF document', 'size' => '3.1 MB'],
                ],
            ],
            [
                'id' => 'UPD-2026-060', 'project' => 'WD-2024-001', 'author' => 'EMP002',
                'day' => -1, 'at' => [19, 5], 'visibility' => self::INTERNAL,
                'title' => 'Holding the CMS migration',
                'body' => 'Not starting the migration until the March invoice is settled — flagged '
                    .'to Vikram. Two days of work is ready to go the moment it clears.',
                'attachments' => [],
            ],
            [
                'id' => 'UPD-2026-059', 'project' => 'WD-2024-001', 'author' => 'EMP002',
                'day' => -1, 'at' => [17, 40], 'visibility' => self::CLIENT,
                'title' => 'Contact form connected',
                'body' => 'Enquiries from the contact form now arrive at the admissions mailbox. '
                    .'We tested it end to end this afternoon.',
                'attachments' => [],
            ],
            [
                'id' => 'UPD-2026-058', 'project' => 'WD-2024-001', 'author' => 'EMP007',
                'day' => -3, 'at' => [18, 30], 'visibility' => self::INTERNAL,
                'title' => 'Third round of banner copy',
                'body' => 'Redoing the banner again — this is the third brief for the same block. '
                    .'Worth raising the change requests at the next call before it eats the buffer.',
                'attachments' => [],
            ],
            [
                'id' => 'UPD-2026-057', 'project' => 'WD-2024-001', 'author' => 'EMP001',
                'day' => -4, 'at' => [17, 55], 'visibility' => self::CLIENT,
                'title' => 'Accessibility pass on the prospectus pages',
                'body' => 'Contrast and heading structure corrected across the prospectus section, '
                    .'so screen readers announce the pages in the right order.',
                'attachments' => [
                    ['name' => 'Accessibility_Notes.pdf', 'kind' => 'PDF document', 'size' => '820 KB'],
                ],
            ],

            // ── CRM Setup (TechNova Solutions) ───────────────────────────────
            [
                'id' => 'UPD-2026-056', 'project' => 'CRM-2024-003', 'author' => 'EMP004',
                'day' => -1, 'at' => [18, 20], 'visibility' => self::CLIENT,
                'title' => 'User roles configured',
                'body' => 'The four roles you listed are set up and we have walked through them '
                    .'with your operations lead.',
                'attachments' => [],
            ],
            [
                'id' => 'UPD-2026-055', 'project' => 'CRM-2024-003', 'author' => 'EMP002',
                'day' => -2, 'at' => [19, 15], 'visibility' => self::INTERNAL,
                'title' => 'Their sandbox credentials keep expiring',
                'body' => 'Third time this week. Asked for a service account rather than somebody '
                    .'re-sharing a personal login every other day.',
                'attachments' => [],
            ],

            // ── E-commerce Storefront (Urban Nest Interiors) ─────────────────
            [
                'id' => 'UPD-2026-054', 'project' => 'ST-2024-011', 'author' => 'EMP008',
                'day' => -2, 'at' => [17, 30], 'visibility' => self::CLIENT,
                'title' => 'Checkout flow ready for walkthrough',
                'body' => 'Card and UPI journeys are both working on staging. We will demo the '
                    .'whole flow at Tuesday\'s meeting.',
                'attachments' => [],
            ],
        ];
    }
}
