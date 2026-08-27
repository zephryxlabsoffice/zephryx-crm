<?php

namespace App\Support\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample tickets for reviewing the Tickets pages before the database exists.
 *
 * Local + debug only, like the other demo sources.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO THINGS HERE ARE THE MODULE'S CONTRACT, NOT SAMPLE DATA
 *
 * 1. A comment carries `visibility` of `public` or `internal`. Internal notes
 *    must never reach the client realm. `commentsFor()` is the only method
 *    that returns them and it takes the audience explicitly — there is no way
 *    to fetch "the comments" without saying who is reading.
 *
 * 2. A client ticket carries `client`. When /client is built, every read must
 *    be scoped to the signed-in client's own tickets (§6). A client seeing
 *    another client's ticket is the worst failure this module can have.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoTickets
{
    public const AUDIENCE_STAFF = 'staff';
    public const AUDIENCE_CLIENT = 'client';

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return collect([
            [
                'id' => 'TKT-2026-152', 'type' => 'internal', 'subject' => 'Unable to access internal dashboard',
                'description' => 'The dashboard has returned a 500 since this morning. Clearing the cache did not help.',
                'raised_by' => 'EMP001', 'client' => null, 'project' => null,
                'assignee' => 'EMP002', 'status' => 'open', 'priority' => 'medium',
                'category' => 'Access', 'department' => 'IT Support',
                'escalated_by' => null, 'created' => '-6 hours', 'updated' => '-2 hours',
            ],
            [
                'id' => 'TKT-2026-151', 'type' => 'client', 'subject' => 'Website not loading on mobile',
                'description' => 'Customers report a blank page on Android. Desktop is fine.',
                'raised_by' => null, 'client' => 'GreenLeaf Foods', 'project' => 'SMC-2024-002',
                'assignee' => 'EMP006', 'status' => 'in_progress', 'priority' => 'high',
                'category' => 'Bug', 'department' => 'Development',
                'escalated_by' => null, 'created' => '-2 days', 'updated' => '-1 day',
            ],
            [
                'id' => 'TKT-2026-150', 'type' => 'internal', 'subject' => 'VPN connection failing frequently',
                'description' => 'The VPN drops every twenty minutes or so on the office connection.',
                'raised_by' => 'EMP004', 'client' => null, 'project' => null,
                'assignee' => 'EMP002', 'status' => 'open', 'priority' => 'low',
                'category' => 'Network', 'department' => 'IT Support',
                'escalated_by' => null, 'created' => '-2 days', 'updated' => '-2 days',
            ],
            [
                'id' => 'TKT-2026-149', 'type' => 'client', 'subject' => 'Payment gateway integration issue',
                'description' => 'Card payments fail at the final step with a generic error.',
                'raised_by' => null, 'client' => 'MediCare Services', 'project' => 'EC-2024-005',
                'assignee' => null, 'status' => 'unassigned', 'priority' => null,
                'category' => null, 'department' => null,
                'escalated_by' => null, 'created' => '-3 days', 'updated' => '-3 days',
            ],
            [
                'id' => 'TKT-2026-148', 'type' => 'client', 'subject' => 'Request to add a new admin user',
                'description' => 'Please add our operations lead to the portal.',
                'raised_by' => null, 'client' => 'DGL International School', 'project' => 'WD-2024-001',
                'assignee' => 'EMP002', 'status' => 'escalated', 'priority' => 'high',
                'category' => 'Access', 'department' => 'Development',
                'escalated_by' => 'EMP006', 'created' => '-5 days', 'updated' => '-8 hours',
            ],
            [
                'id' => 'TKT-2026-147', 'type' => 'internal', 'subject' => 'Payroll export missing a column',
                'description' => 'The salary export is missing the PF column since last month.',
                'raised_by' => 'EMP005', 'client' => null, 'project' => null,
                'assignee' => 'EMP004', 'status' => 'resolved', 'priority' => 'medium',
                'category' => 'Reporting', 'department' => 'Development',
                'escalated_by' => null, 'created' => '-12 days', 'updated' => '-6 days',
            ],
            [
                'id' => 'TKT-2026-146', 'type' => 'client', 'subject' => 'Slow load on the storefront',
                'description' => 'Product pages take eight seconds or more to appear.',
                'raised_by' => null, 'client' => 'Urban Nest Interiors', 'project' => 'ST-2024-011',
                'assignee' => 'EMP002', 'status' => 'in_progress', 'priority' => 'medium',
                'category' => 'Performance', 'department' => 'Development',
                'escalated_by' => null, 'created' => '-4 days', 'updated' => '-1 day',
            ],
            [
                'id' => 'TKT-2026-145', 'type' => 'internal', 'subject' => 'Laptop replacement request',
                'description' => 'The battery no longer holds charge for more than twenty minutes.',
                'raised_by' => 'EMP003', 'client' => null, 'project' => null,
                'assignee' => null, 'status' => 'unassigned', 'priority' => null,
                'category' => null, 'department' => null,
                'escalated_by' => null, 'created' => '-1 day', 'updated' => '-1 day',
            ],
            [
                'id' => 'TKT-2026-144', 'type' => 'client', 'subject' => 'Invoice shows the wrong tax rate',
                'description' => 'The last invoice applied 18% where it should have been 5%.',
                'raised_by' => null, 'client' => 'TechNova Solutions', 'project' => 'CRM-2024-003',
                'assignee' => 'EMP006', 'status' => 'escalated', 'priority' => 'high',
                'category' => 'Billing', 'department' => 'Operations',
                'escalated_by' => 'EMP002', 'created' => '-7 days', 'updated' => '-3 hours',
            ],
            [
                'id' => 'TKT-2026-143', 'type' => 'internal', 'subject' => 'Add me to the design team drive',
                'description' => 'I cannot open the shared brand folder.',
                'raised_by' => 'EMP002', 'client' => null, 'project' => null,
                'assignee' => 'EMP001', 'status' => 'resolved', 'priority' => 'low',
                'category' => 'Access', 'department' => 'IT Support',
                'escalated_by' => null, 'created' => '-20 days', 'updated' => '-18 days',
            ],
        ])->map(function (array $ticket) {
            $ticket['created_at'] = Carbon::now()->modify($ticket['created']);
            $ticket['updated_at'] = Carbon::now()->modify($ticket['updated']);

            return $ticket;
        });
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /**
     * Tickets the signed-in person raised. Stands in with a fixed user until
     * authentication lands.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function raisedBy(string $userId = 'EMP002'): Collection
    {
        return self::all()->where('raised_by', $userId)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function assignedTo(string $userId = 'EMP002'): Collection
    {
        return self::all()->where('assignee', $userId)->values();
    }

    /**
     * Tickets against the projects the signed-in person works on.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function onMyProjects(string $userId = 'EMP002'): Collection
    {
        $projects = DemoProjects::mine($userId)->pluck('id');

        return self::all()
            ->filter(fn (array $t) => $t['project'] && $projects->contains($t['project']))
            ->values();
    }

    /**
     * The review queue. Escalation is flat: one shared queue that whoever
     * holds the triage permission works through, rather than an L1→L2→L3
     * ladder (decided 2026-08-27 — with six staff most rungs would be empty).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function escalated(): Collection
    {
        return self::all()->where('status', 'escalated')->values();
    }

    /**
     * Tickets nobody has picked up. The triage queue.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function unassigned(): Collection
    {
        return self::all()->where('status', 'unassigned')->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|null  $tickets
     * @return array<string, int>
     */
    public static function stats(?Collection $tickets = null): array
    {
        $tickets ??= self::all();

        return [
            'total' => $tickets->count(),
            'unassigned' => $tickets->where('status', 'unassigned')->count(),
            'escalated' => $tickets->where('status', 'escalated')->count(),
            'open' => $tickets->where('status', 'open')->count(),
            'in_progress' => $tickets->where('status', 'in_progress')->count(),
            'resolved' => $tickets->where('status', 'resolved')->count(),
        ];
    }

    /**
     * The thread on a ticket, for a stated audience.
     *
     * The audience is a required argument on purpose. There is deliberately no
     * `comments($ticket)` that returns everything — a caller must say who is
     * reading, so that forgetting to filter is a syntax error rather than a
     * client reading an internal note.
     *
     * @return list<array<string, mixed>>
     */
    public static function commentsFor(array $ticket, string $audience): array
    {
        if (! self::enabled()) {
            return [];
        }

        $all = self::thread($ticket);

        if ($audience === self::AUDIENCE_CLIENT) {
            return array_values(array_filter(
                $all,
                fn (array $comment) => $comment['visibility'] === 'public'
            ));
        }

        return $all;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function thread(array $ticket): array
    {
        $base = $ticket['created_at'];

        return match ($ticket['id']) {
            'TKT-2026-151' => [
                ['author' => 'GreenLeaf Foods', 'role' => 'Client', 'visibility' => 'public', 'at' => $base->copy()->addMinutes(5),
                 'body' => 'Two of our customers reported this yesterday evening. Screenshots attached.'],
                ['author' => 'Vikram Joshi', 'role' => 'Support Engineer', 'visibility' => 'internal', 'at' => $base->copy()->addHours(2),
                 'body' => 'Reproduced on Android Chrome. Looks like the hero image is not being served in WebP — the CDN rule from last week is the likely cause. Not telling them that until I have confirmed it.'],
                ['author' => 'Vikram Joshi', 'role' => 'Support Engineer', 'visibility' => 'public', 'at' => $base->copy()->addHours(3),
                 'body' => 'Thanks — we have reproduced it and are working on a fix. We will update you today.'],
            ],
            'TKT-2026-152' => [
                ['author' => 'Amit Verma', 'role' => 'Frontend Developer', 'visibility' => 'internal', 'at' => $base->copy()->addHours(1),
                 'body' => 'Looks like the deploy this morning missed a migration. Rolling it forward now.'],
            ],
            'TKT-2026-144' => [
                ['author' => 'TechNova Solutions', 'role' => 'Client', 'visibility' => 'public', 'at' => $base->copy()->addMinutes(10),
                 'body' => 'Could you re-issue the invoice with the correct rate?'],
                ['author' => 'Amit Verma', 'role' => 'Frontend Developer', 'visibility' => 'internal', 'at' => $base->copy()->addDay(),
                 'body' => 'Escalating — this needs someone who can re-issue an invoice, which I cannot do.'],
            ],
            default => [],
        };
    }

    /**
     * Files on a ticket. Client uploads are untrusted input: §6 requires them
     * validated by type and size, stored outside the web root and served
     * through an authorising controller — never linked directly.
     *
     * @return list<array<string, string>>
     */
    public static function attachments(array $ticket): array
    {
        if (! self::enabled() || $ticket['id'] !== 'TKT-2026-151') {
            return [];
        }

        return [
            ['name' => 'android-blank-page.png', 'kind' => 'Screenshot', 'size' => '412 KB'],
            ['name' => 'console-output.txt', 'kind' => 'Text file', 'size' => '3 KB'],
        ];
    }
}
