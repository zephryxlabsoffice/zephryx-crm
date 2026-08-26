<?php

namespace App\Support\Demo;

use Illuminate\Support\Collection;

/**
 * Sample employee rows for reviewing the Employees page before the database
 * exists. Local + debug only, like DemoClients — everywhere else this returns
 * nothing, so a deployed site shows its empty states rather than invented
 * people.
 *
 * Deleted when the Employees module gets its migration and model.
 */
class DemoEmployees
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
            ['user_id' => 'EMP001', 'name' => 'Riya Sharma',    'department' => 'Design',      'designation' => 'UI/UX Designer',      'email' => 'riya.sharma@zephryxlabs.com',    'status' => 'active',   'joined' => '2024-01-12'],
            ['user_id' => 'EMP002', 'name' => 'Amit Verma',     'department' => 'Development', 'designation' => 'Frontend Developer',  'email' => 'amit.verma@zephryxlabs.com',     'status' => 'active',   'joined' => '2024-02-15'],
            ['user_id' => 'EMP003', 'name' => 'Neha Patel',     'department' => 'Marketing',   'designation' => 'Digital Marketer',    'email' => 'neha.patel@zephryxlabs.com',     'status' => 'active',   'joined' => '2024-01-10'],
            ['user_id' => 'EMP004', 'name' => 'Rahul Mehta',    'department' => 'Development', 'designation' => 'Backend Developer',   'email' => 'rahul.mehta@zephryxlabs.com',    'status' => 'active',   'joined' => '2024-03-01'],
            ['user_id' => 'EMP005', 'name' => 'Pooja Singh',    'department' => 'HR',          'designation' => 'HR Executive',        'email' => 'pooja.singh@zephryxlabs.com',    'status' => 'on_leave', 'joined' => '2024-02-18'],
            ['user_id' => 'EMP006', 'name' => 'Vikram Joshi',   'department' => 'Finance',     'designation' => 'Accountant',          'email' => 'vikram.joshi@zephryxlabs.com',   'status' => 'active',   'joined' => '2024-01-05'],
            ['user_id' => 'EMP007', 'name' => 'Anjali Desai',   'department' => 'Support',     'designation' => 'Support Specialist',  'email' => 'anjali.desai@zephryxlabs.com',   'status' => 'active',   'joined' => '2024-03-22'],
            ['user_id' => 'EMP008', 'name' => 'Karan Malhotra', 'department' => 'Sales',       'designation' => 'Sales Executive',     'email' => 'karan.malhotra@zephryxlabs.com', 'status' => 'active',   'joined' => '2024-04-28'],
            ['user_id' => 'EMP009', 'name' => 'Sneha Kapoor',   'department' => 'Marketing',   'designation' => 'Marketing Executive', 'email' => 'sneha.kapoor@zephryxlabs.com',   'status' => 'active',   'joined' => '2026-08-24'],
            ['user_id' => 'EMP010', 'name' => 'Arjun Nair',     'department' => 'Development', 'designation' => 'Frontend Developer',  'email' => 'arjun.nair@zephryxlabs.com',     'status' => 'active',   'joined' => '2026-08-21'],
            ['user_id' => 'EMP011', 'name' => 'Meera Iyer',     'department' => 'HR',          'designation' => 'HR Executive',        'email' => 'meera.iyer@zephryxlabs.com',     'status' => 'active',   'joined' => '2026-08-19'],
            ['user_id' => 'EMP012', 'name' => 'Dev Chatterjee', 'department' => 'Design',      'designation' => 'Motion Designer',     'email' => 'dev.chatterjee@zephryxlabs.com', 'status' => 'inactive', 'joined' => '2023-11-02'],
        ]);
    }

    /**
     * @return array<string, int>
     */
    public static function stats(): array
    {
        $employees = self::all();

        return [
            'total' => $employees->count(),
            'active' => $employees->where('status', 'active')->count(),
            // Leave does not exist yet; this reads zero until it does.
            'on_leave' => $employees->where('status', 'on_leave')->count(),
            'new_this_month' => $employees->filter(
                fn (array $row) => str_starts_with($row['joined'], now()->format('Y-m'))
            )->count(),
        ];
    }

    /**
     * Headcount per department, largest first — the donut's data.
     *
     * @return list<array{name: string, count: int, share: float}>
     */
    public static function byDepartment(): array
    {
        $employees = self::all();
        $total = $employees->count();

        if ($total === 0) {
            return [];
        }

        return $employees
            ->groupBy('department')
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'count' => $rows->count(),
                'share' => round($rows->count() / $total * 100, 1),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * The most recent starters.
     *
     * @return list<array<string, string>>
     */
    public static function recentStarters(int $limit = 3): array
    {
        return self::all()
            ->sortByDesc('joined')
            ->take($limit)
            ->map(fn (array $row) => [
                'name' => $row['name'],
                'designation' => $row['designation'],
                'when' => \Illuminate\Support\Carbon::parse($row['joined'])->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /**
     * Upcoming birthdays.
     *
     * Empty on purpose: date of birth is a field on the Employees module that
     * does not exist yet, and it is not something to invent — the card renders
     * its own empty state until the module supplies real dates.
     *
     * @return list<array<string, string>>
     */
    public static function birthdays(): array
    {
        return [];
    }
}
