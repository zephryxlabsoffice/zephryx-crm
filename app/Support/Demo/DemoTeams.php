<?php

namespace App\Support\Demo;

use Illuminate\Support\Collection;

/**
 * Sample teams for reviewing the Teams pages before the database exists.
 *
 * Local + debug only, like DemoClients and DemoEmployees. Members are drawn
 * from DemoEmployees so the two modules agree with each other — a team whose
 * members do not exist in the employee list would be a confusing thing to
 * review.
 */
class DemoTeams
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
            ['id' => 'TM-1001', 'name' => 'Design Team',              'purpose' => 'UI/UX and product design',      'lead' => 'EMP001', 'status' => 'active',   'created' => '2024-01-12', 'members' => ['EMP001', 'EMP012', 'EMP003', 'EMP007']],
            ['id' => 'TM-1002', 'name' => 'Web Development Team',     'purpose' => 'Frontend development',          'lead' => 'EMP002', 'status' => 'active',   'created' => '2024-01-18', 'members' => ['EMP002', 'EMP010', 'EMP004', 'EMP001']],
            ['id' => 'TM-1003', 'name' => 'Backend Development Team', 'purpose' => 'Server-side development',       'lead' => 'EMP004', 'status' => 'active',   'created' => '2024-01-22', 'members' => ['EMP004', 'EMP002']],
            ['id' => 'TM-1004', 'name' => 'Quality Assurance Team',   'purpose' => 'Testing and QA',                'lead' => 'EMP007', 'status' => 'active',   'created' => '2024-02-05', 'members' => ['EMP007', 'EMP004', 'EMP010']],
            ['id' => 'TM-1005', 'name' => 'Project Management Team',  'purpose' => 'Project planning and delivery', 'lead' => 'EMP006', 'status' => 'active',   'created' => '2024-02-15', 'members' => ['EMP006', 'EMP005', 'EMP008']],
            ['id' => 'TM-1006', 'name' => 'Business Analysis Team',   'purpose' => 'Requirements and analysis',     'lead' => 'EMP008', 'status' => 'active',   'created' => '2024-03-01', 'members' => ['EMP008', 'EMP006']],
            ['id' => 'TM-1007', 'name' => 'Operations Team',          'purpose' => 'Operations and support',        'lead' => 'EMP007', 'status' => 'inactive', 'created' => '2024-03-10', 'members' => ['EMP007', 'EMP012']],
            ['id' => 'TM-1008', 'name' => 'Human Resources Team',     'purpose' => 'HR and administration',         'lead' => 'EMP005', 'status' => 'active',   'created' => '2024-03-20', 'members' => ['EMP005', 'EMP011']],
            ['id' => 'TM-1009', 'name' => 'Digital Marketing Team',   'purpose' => 'Campaigns and content',         'lead' => 'EMP003', 'status' => 'active',   'created' => '2024-04-08', 'members' => ['EMP003', 'EMP009']],
            ['id' => 'TM-1010', 'name' => 'Mobile App Team',          'purpose' => 'iOS and Android delivery',      'lead' => null,     'status' => 'active',   'created' => '2026-08-14', 'members' => ['EMP002', 'EMP010']],
        ]);
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /**
     * Full employee rows for a team's members, in the order stored.
     *
     * @return list<array<string, mixed>>
     */
    public static function membersOf(array $team): array
    {
        $employees = DemoEmployees::all()->keyBy('user_id');

        return collect($team['members'])
            ->map(fn (string $id) => $employees->get($id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The teams the signed-in person belongs to.
     *
     * No session exists yet, so this stands in with a fixed member. Once
     * authentication lands it filters on the actual viewer.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function mine(string $userId = 'EMP002'): Collection
    {
        return self::all()->filter(
            fn (array $team) => in_array($userId, $team['members'], true)
        )->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|null  $teams
     * @return array<string, int>
     */
    public static function stats(?Collection $teams = null): array
    {
        $teams ??= self::all();

        return [
            'total' => $teams->count(),
            'active' => $teams->where('status', 'active')->count(),
            'inactive' => $teams->where('status', 'inactive')->count(),
        ];
    }

    /**
     * Recent changes to teams.
     *
     * Reads from the audit log (§8) once that exists; the shape here matches
     * what it will supply.
     *
     * @return list<array<string, string>>
     */
    public static function activity(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return [
            ['what' => 'Mobile App Team was created',        'who' => 'Santanu Dev', 'when' => '14 Aug 2026, 3:45 PM',  'tone' => ''],
            ['what' => 'Operations Team was deactivated',    'who' => 'Santanu Dev', 'when' => '12 Aug 2026, 11:10 AM', 'tone' => 'tone-warn'],
            ['what' => 'Digital Marketing Team was created', 'who' => 'Santanu Dev', 'when' => '08 Apr 2026, 4:50 PM',  'tone' => ''],
            ['what' => 'Quality Assurance Team updated',     'who' => 'Santanu Dev', 'when' => '02 Apr 2026, 9:20 AM',  'tone' => 'tone-accent'],
            ['what' => 'Human Resources Team updated',       'who' => 'Santanu Dev', 'when' => '28 Mar 2026, 10:15 AM', 'tone' => 'tone-alt'],
        ];
    }
}
