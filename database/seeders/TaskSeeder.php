<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Support\Demo\DemoTasks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The demo tasks. Local + debug only.
 *
 * Runs after ProjectSeeder: every task points at a project, most at a team, and
 * many at a person.
 *
 * The fixture's unassigned tasks are kept unassigned deliberately — they are
 * the Team Lead's queue, and a seed where everything already has somebody on it
 * would hide the state `/tasks/team` exists for.
 */
class TaskSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $projects = Project::pluck('id', 'reference');
        $teams = Team::pluck('id', 'reference');
        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        foreach (DemoTasks::all() as $row) {
            $task = Task::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'project_id' => $projects[$row['project']] ?? null,
                    'team_id' => $row['team'] ? ($teams[$row['team']] ?? null) : null,
                    'assignee_id' => $row['assignee'] ? $employees->get($row['assignee'])?->id : null,
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'due_on' => $row['due'],
                    'completed_at' => $row['status'] === 'completed'
                        ? Carbon::parse($row['due'])->setTime(16, 45)
                        : null,
                ],
            );

            /*
             * The task's own age, not the seeder's. `created_at` is what the
             * detail page prints as "Created", and every demo task stamped with
             * the moment the seeder ran would make a fourteen-task board look
             * like it was all started in the same second.
             */
            $created = Carbon::parse($row['created_at'])->setTime(10, 30);
            $task->forceFill(['created_at' => $created])->saveQuietly();
        }
    }
}
