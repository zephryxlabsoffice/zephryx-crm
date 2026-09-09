<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Team;
use App\Support\Demo\DemoTeams;
use Illuminate\Database\Seeder;

/**
 * The demo teams and their membership. Local + debug only.
 *
 * Runs after EmployeeSeeder, because every lead and every member is an
 * employment record — a team pointing at people who do not exist is a page of
 * empty rows.
 *
 * Nothing here runs in production: real teams are created through the module by
 * whoever is actually running one.
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        // Staff id → employment record, resolved once rather than per member.
        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        foreach (DemoTeams::all() as $row) {
            $team = Team::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'name' => $row['name'],
                    'purpose' => $row['purpose'],
                    // A team with no lead is a real state the fixture includes
                    // on purpose, so this resolves to null rather than assuming
                    // there is one to look up.
                    'lead_id' => $row['lead'] ? $employees->get($row['lead'])?->id : null,
                    'status' => $row['status'],
                    'formed_on' => $row['created'],
                ],
            );

            /*
             * sync, not syncWithoutDetaching: re-running the seeder should
             * leave the demo teams exactly as the fixture describes them,
             * including anybody a previous run added and the fixture has since
             * dropped.
             */
            $team->members()->sync(
                collect($row['members'])
                    ->map(fn (string $staffId) => $employees->get($staffId)?->id)
                    ->filter()
                    ->mapWithKeys(fn (int $id) => [$id => ['joined_at' => $row['created']]])
                    ->all()
            );
        }
    }
}
