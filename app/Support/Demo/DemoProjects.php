<?php

namespace App\Support\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample projects for reviewing the Projects pages before the database exists.
 *
 * Local + debug only. Clients and managers are drawn from DemoClients and
 * DemoEmployees so the modules agree with each other.
 *
 * Deadlines are stored as an offset in days from today rather than as fixed
 * dates: the handover's dates were all in 2024 and would read as long overdue
 * by now, which makes the "upcoming deadlines" and "overdue" states impossible
 * to review.
 */
class DemoProjects
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
            ['id' => 'WD-2024-001',  'name' => 'Website Redesign',       'client' => 'DGL International School', 'manager' => 'EMP001', 'progress' => 75,  'due_in' => 5,   'status' => 'in_progress', 'priority' => 'high',   'started' => '-3 months', 'teams' => ['TM-1001', 'TM-1002', 'TM-1004']],
            ['id' => 'SMC-2024-002', 'name' => 'Social Media Campaign',  'client' => 'GreenLeaf Foods',          'manager' => 'EMP003', 'progress' => 60,  'due_in' => 8,   'status' => 'in_progress', 'priority' => 'medium', 'started' => '-2 months', 'teams' => ['TM-1009']],
            ['id' => 'CRM-2024-003', 'name' => 'CRM Setup',              'client' => 'TechNova Solutions',       'manager' => 'EMP002', 'progress' => 35,  'due_in' => 16,  'status' => 'planning',    'priority' => 'high',   'started' => '-1 month',  'teams' => ['TM-1002', 'TM-1003']],
            ['id' => 'BR-2024-004',  'name' => 'Branding Kit',           'client' => 'ABC Pvt Ltd',              'manager' => 'EMP004', 'progress' => 90,  'due_in' => -4,  'status' => 'review',      'priority' => 'medium', 'started' => '-4 months', 'teams' => ['TM-1001']],
            ['id' => 'EC-2024-005',  'name' => 'E-commerce Development', 'client' => 'Innovate Hub',             'manager' => 'EMP006', 'progress' => 20,  'due_in' => 31,  'status' => 'in_progress', 'priority' => 'high',   'started' => '-1 month',  'teams' => ['TM-1002', 'TM-1003', 'TM-1004']],
            ['id' => 'APP-2024-006', 'name' => 'Mobile App Development', 'client' => 'Bright Future Academy',    'manager' => 'EMP007', 'progress' => 15,  'due_in' => 41,  'status' => 'planning',    'priority' => 'low',    'started' => '-2 weeks', 'teams' => ['TM-1010']],
            ['id' => 'CW-2024-007',  'name' => 'Content Writing',        'client' => 'MediCare Services',        'manager' => 'EMP008', 'progress' => 100, 'due_in' => -20, 'status' => 'completed',   'priority' => 'low',    'started' => '-5 months', 'teams' => ['TM-1009']],
            ['id' => 'SEO-2024-008', 'name' => 'SEO Optimisation',       'client' => 'DGL International School', 'manager' => 'EMP003', 'progress' => 50,  'due_in' => 21,  'status' => 'on_hold',     'priority' => 'medium', 'started' => '-3 months', 'teams' => ['TM-1009']],
            ['id' => 'LMS-2024-009', 'name' => 'Learning Platform',      'client' => 'Bright Future Academy',    'manager' => 'EMP002', 'progress' => 45,  'due_in' => -2,  'status' => 'in_progress', 'priority' => 'high',   'started' => '-4 months', 'teams' => ['TM-1002', 'TM-1004']],
            ['id' => 'FL-2024-010',  'name' => 'Fleet Tracking',         'client' => 'Sunrise Logistics',        'manager' => 'EMP004', 'progress' => 10,  'due_in' => 55,  'status' => 'planning',    'priority' => 'medium', 'started' => '-1 week',  'teams' => ['TM-1003']],
            ['id' => 'ST-2024-011',  'name' => 'Storefront Build',       'client' => 'Urban Nest Interiors',     'manager' => 'EMP006', 'progress' => 80,  'due_in' => 12,  'status' => 'in_progress', 'priority' => 'medium', 'started' => '-2 months', 'teams' => ['TM-1002']],
            ['id' => 'PH-2024-012',  'name' => 'Brand Photography',      'client' => 'Kolkata Craft Collective', 'manager' => 'EMP001', 'progress' => 100, 'due_in' => -35, 'status' => 'completed',   'priority' => 'low',    'started' => '-6 months', 'teams' => ['TM-1001']],
        ])->map(function (array $project) {
            $project['deadline'] = Carbon::today()->addDays($project['due_in'])->toDateString();
            $project['start_date'] = Carbon::today()->modify($project['started'])->toDateString();

            return $project;
        });
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /**
     * The projects the signed-in person manages or works on.
     *
     * No session exists yet, so this stands in with a fixed manager. Once
     * authentication lands it filters on the actual viewer.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function mine(string $userId = 'EMP002'): Collection
    {
        return self::all()->where('manager', $userId)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|null  $projects
     * @return array<string, int>
     */
    public static function stats(?Collection $projects = null): array
    {
        $projects ??= self::all();

        return [
            'total' => $projects->count(),
            'active' => $projects->whereIn('status', ['in_progress', 'planning', 'review'])->count(),
            'completed' => $projects->where('status', 'completed')->count(),
            'on_hold' => $projects->where('status', 'on_hold')->count(),
            // Past its deadline and not finished. A completed project that
            // landed late is not an outstanding problem.
            'overdue' => $projects->filter(
                fn (array $p) => $p['due_in'] < 0 && $p['status'] !== 'completed'
            )->count(),
        ];
    }

    /**
     * The next deadlines still ahead of us, soonest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function upcoming(int $limit = 4): array
    {
        return self::all()
            ->where('status', '!=', 'completed')
            ->sortBy('due_in')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The teams assigned to a project, as full team records.
     *
     * @return list<array<string, mixed>>
     */
    public static function teamsOf(array $project): array
    {
        $teams = DemoTeams::all()->keyBy('id');
        $employees = DemoEmployees::all()->keyBy('user_id');

        return collect($project['teams'])
            ->map(fn (string $id) => $teams->get($id))
            ->filter()
            ->map(function (array $team) use ($employees) {
                $team['lead_record'] = $team['lead'] ? $employees->get($team['lead']) : null;
                $team['member_count'] = count($team['members']);

                return $team;
            })
            ->values()
            ->all();
    }
}
