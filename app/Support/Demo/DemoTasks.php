<?php

namespace App\Support\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample tasks for reviewing the Tasks pages before the database exists.
 *
 * Local + debug only. Projects, teams and people are drawn from the other demo
 * sources so the modules agree with each other.
 *
 * Like DemoProjects, due dates are offsets from today rather than fixed dates —
 * the handover's were all in 2024 and would read as universally overdue.
 *
 * A task is assigned either to a whole team (`assignee` null, `team` set) or to
 * one person. Both are real states; the "assigned to a team but nobody in it"
 * case is what the overview's banner is for.
 */
class DemoTasks
{
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
            ['id' => 'TSK-001', 'name' => 'Design homepage layout',    'project' => 'WD-2024-001',  'team' => 'TM-1001', 'assignee' => null,     'status' => 'in_progress', 'priority' => 'high',   'due_in' => 2,   'created' => '-7 days'],
            ['id' => 'TSK-002', 'name' => 'Social media creatives',    'project' => 'SMC-2024-002', 'team' => 'TM-1009', 'assignee' => 'EMP003', 'status' => 'in_progress', 'priority' => 'medium', 'due_in' => 5,   'created' => '-10 days'],
            ['id' => 'TSK-003', 'name' => 'CRM module development',    'project' => 'CRM-2024-003', 'team' => 'TM-1003', 'assignee' => 'EMP002', 'status' => 'in_progress', 'priority' => 'high',   'due_in' => 13,  'created' => '-14 days'],
            ['id' => 'TSK-004', 'name' => 'Brand guidelines',          'project' => 'BR-2024-004',  'team' => 'TM-1001', 'assignee' => null,     'status' => 'review',      'priority' => 'medium', 'due_in' => -3,  'created' => '-30 days'],
            ['id' => 'TSK-005', 'name' => 'Contact form integration',  'project' => 'WD-2024-001',  'team' => 'TM-1002', 'assignee' => 'EMP004', 'status' => 'completed',   'priority' => 'high',   'due_in' => -8,  'created' => '-25 days'],
            ['id' => 'TSK-006', 'name' => 'Blog writing',              'project' => 'CW-2024-007',  'team' => 'TM-1009', 'assignee' => 'EMP008', 'status' => 'pending',     'priority' => 'low',    'due_in' => 7,   'created' => '-4 days'],
            ['id' => 'TSK-007', 'name' => 'Database backup routine',   'project' => 'CRM-2024-003', 'team' => 'TM-1003', 'assignee' => 'EMP006', 'status' => 'pending',     'priority' => 'low',    'due_in' => 8,   'created' => '-3 days'],
            ['id' => 'TSK-008', 'name' => 'QA and testing',            'project' => 'APP-2024-006', 'team' => 'TM-1004', 'assignee' => 'EMP007', 'status' => 'in_progress', 'priority' => 'medium', 'due_in' => 28,  'created' => '-2 days'],
            ['id' => 'TSK-009', 'name' => 'Payment gateway hookup',    'project' => 'EC-2024-005',  'team' => 'TM-1003', 'assignee' => 'EMP002', 'status' => 'pending',     'priority' => 'high',   'due_in' => 0,   'created' => '-6 days'],
            ['id' => 'TSK-010', 'name' => 'Accessibility audit',       'project' => 'WD-2024-001',  'team' => 'TM-1004', 'assignee' => null,     'status' => 'pending',     'priority' => 'medium', 'due_in' => 4,   'created' => '-1 day'],
            ['id' => 'TSK-011', 'name' => 'Fleet map prototype',       'project' => 'FL-2024-010',  'team' => 'TM-1003', 'assignee' => 'EMP004', 'status' => 'in_progress', 'priority' => 'medium', 'due_in' => 19,  'created' => '-5 days'],
            ['id' => 'TSK-012', 'name' => 'Storefront copy review',    'project' => 'ST-2024-011',  'team' => 'TM-1009', 'assignee' => 'EMP008', 'status' => 'completed',   'priority' => 'low',    'due_in' => -12, 'created' => '-40 days'],
            ['id' => 'TSK-013', 'name' => 'Photo retouching',          'project' => 'PH-2024-012',  'team' => 'TM-1001', 'assignee' => 'EMP001', 'status' => 'completed',   'priority' => 'low',    'due_in' => -30, 'created' => '-60 days'],
            ['id' => 'TSK-014', 'name' => 'Learning platform schema',  'project' => 'LMS-2024-009', 'team' => 'TM-1003', 'assignee' => 'EMP002', 'status' => 'in_progress', 'priority' => 'high',   'due_in' => -1,  'created' => '-20 days'],
        ])->map(function (array $task) {
            $task['due'] = Carbon::today()->addDays($task['due_in'])->toDateString();
            $task['created_at'] = Carbon::today()->modify($task['created'])->toDateString();

            return $task;
        });
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /**
     * Tasks assigned to the signed-in person. Stands in with a fixed user
     * until authentication lands.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function mine(string $userId = 'EMP002'): Collection
    {
        return self::all()->where('assignee', $userId)->values();
    }

    /**
     * Tasks held by a whole team rather than an individual — the Team Lead's
     * queue, and the ones that still need someone put on them.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function teamTasks(): Collection
    {
        return self::all()->whereNull('assignee')->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|null  $tasks
     * @return array<string, int>
     */
    public static function stats(?Collection $tasks = null): array
    {
        $tasks ??= self::all();

        return [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            // Past due and not finished. A task delivered late is history.
            'overdue' => $tasks->filter(
                fn (array $t) => $t['due_in'] < 0 && $t['status'] !== 'completed'
            )->count(),
            'due_today' => $tasks->filter(
                fn (array $t) => $t['due_in'] === 0 && $t['status'] !== 'completed'
            )->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function upcoming(int $limit = 5): array
    {
        return self::all()
            ->where('status', '!=', 'completed')
            ->where('due_in', '>=', 0)
            ->sortBy('due_in')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * What has happened to a task, oldest first.
     *
     * Reads from the audit log (§8) once that exists; the shape matches.
     *
     * @return list<array<string, string>>
     */
    public static function timeline(array $task): array
    {
        if (! self::enabled()) {
            return [];
        }

        // Dates are stored without a time; give the events a plausible hour so
        // the timeline does not read as a column of "12:00 AM".
        $created = Carbon::parse($task['created_at'])->setTime(10, 30);

        $events = [
            ['when' => $created->format('d M Y, g:i A'), 'what' => 'Task created', 'who' => 'Santanu Dev'],
        ];

        if ($task['assignee']) {
            $person = DemoEmployees::all()->firstWhere('user_id', $task['assignee']);
            $events[] = [
                'when' => $created->copy()->addHours(1)->format('d M Y, g:i A'),
                'what' => 'Assigned to '.($person['name'] ?? $task['assignee']),
                'who' => 'Santanu Dev',
            ];
        }

        if (in_array($task['status'], ['in_progress', 'review', 'completed'], true)) {
            $events[] = [
                'when' => $created->copy()->addDay()->format('d M Y, g:i A'),
                'what' => 'Status changed to In Progress',
                'who' => 'Amit Verma',
            ];
        }

        if ($task['status'] === 'completed') {
            $events[] = [
                'when' => Carbon::parse($task['due'])->setTime(16, 45)->format('d M Y, g:i A'),
                'what' => 'Marked as completed',
                'who' => 'Amit Verma',
            ];
        }

        return $events;
    }

    /**
     * Files on a task.
     *
     * @return list<array<string, string>>
     */
    public static function attachments(array $task): array
    {
        if (! self::enabled() || $task['id'] !== 'TSK-001') {
            return [];
        }

        return [
            ['name' => 'Homepage_Wireframe.fig', 'kind' => 'Figma file', 'size' => '2.4 MB'],
            ['name' => 'Brand_Guidelines.pdf', 'kind' => 'PDF document', 'size' => '1.8 MB'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function activity(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return [
            ['who' => 'Santanu Dev',    'what' => 'assigned "Design homepage layout" to Design Team',    'when' => '2 hours ago', 'tone' => ''],
            ['who' => 'Rahul Mehta',    'what' => 'assigned "CRM module development" to Amit Verma',     'when' => '4 hours ago', 'tone' => 'tone-alt'],
            ['who' => 'Amit Verma',     'what' => 'moved "CRM module development" to In Progress',       'when' => '1 day ago',   'tone' => 'tone-accent'],
            ['who' => 'Rahul Mehta',    'what' => 'completed "Contact form integration"',                'when' => '2 days ago',  'tone' => ''],
        ];
    }
}
