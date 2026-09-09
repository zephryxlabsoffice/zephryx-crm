<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\Team;
use App\Support\Demo\DemoProjects;
use App\Support\Demo\DemoProjectUpdates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The demo projects, their team assignments and their end-of-day updates.
 * Local + debug only.
 *
 * Runs last: every project points at a client, most at a manager, and each
 * update at an employee and a project.
 *
 * The demo updates are reproduced INCLUDING their internal ones, because those
 * are the point — a seed where every note is client-visible would hide the
 * whole reason the visibility column exists.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $clients = Client::pluck('id', 'name');
        $teams = Team::pluck('id', 'reference');
        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        foreach (DemoProjects::all() as $row) {
            $clientId = $clients[$row['client']] ?? null;

            if ($clientId === null) {
                // A project with no client is not a project this schema can
                // hold — see the migration. Skipped rather than invented.
                continue;
            }

            $project = Project::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'name' => $row['name'],
                    'client_id' => $clientId,
                    'manager_id' => $employees->get($row['manager'])?->id,
                    'progress' => $row['progress'],
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'started_on' => $row['start_date'],
                    'deadline' => $row['deadline'],
                ],
            );

            $project->teams()->sync(
                collect($row['teams'])
                    ->map(fn (string $reference) => $teams[$reference] ?? null)
                    ->filter()
                    ->all()
            );
        }

        $this->updates($employees);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Employee>  $employees
     */
    protected function updates($employees): void
    {
        $projects = Project::pluck('id', 'reference');

        foreach (DemoProjectUpdates::seedRows() as $row) {
            $projectId = $projects[$row['project']] ?? null;
            $author = $employees->get($row['author']);

            if ($projectId === null || $author === null) {
                continue;
            }

            $postedAt = Carbon::today()->addDays($row['day'])->setTime($row['at'][0], $row['at'][1]);

            $update = ProjectUpdate::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'project_id' => $projectId,
                    'author_id' => $author->id,
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'visibility' => $row['visibility'],
                    'published_at' => $row['visibility'] === ProjectUpdate::CLIENT ? $postedAt : null,
                ],
            );

            /*
             * The timestamps are set after the fact because `created_at` is
             * what the log orders by, and Eloquent would otherwise stamp every
             * demo update with the moment the seeder ran — a log where
             * everything happened at once.
             */
            $update->forceFill(['created_at' => $postedAt, 'updated_at' => $postedAt])->saveQuietly();
        }
    }
}
