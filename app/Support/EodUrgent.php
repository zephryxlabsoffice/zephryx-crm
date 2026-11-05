<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EodUrgentRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * A missing End of Day that a manager or team lead needs now (owner, 2026-11-05).
 *
 * Filing late is normal: somebody who left in a rush files it the next day. This is
 * for when that cannot wait. The person gets a bell, an email, a device alert if they
 * turned those on, and a notice on every page until they file, which is what clears it.
 *
 * Their reporting manager, team lead and the CEO can always ask. A project manager can
 * too, for people on their projects, while PROJECT_PERMISSION is held (owner,
 * 2026-11-05: useful at this size, and switched off from Access Controls later).
 *
 * The notice is on every page, so it must tolerate a database the update has not
 * reached yet.
 */
class EodUrgent
{
    public const KIND = 'eod_urgent';

    public const PROJECT_PERMISSION = 'tasks.eod.urgent';

    public static function isReady(): bool
    {
        return Schema::hasTable('eod_urgent_requests');
    }

    /**
     * What this person still has to file urgently, oldest first.
     *
     * @return Collection<int, EodUrgentRequest>
     */
    public static function waitingFor(?User $user): Collection
    {
        if ($user === null || ! $user->hasEmployeeBase() || ! EodPolicy::isLive() || ! self::isReady()) {
            return collect();
        }

        return EodUrgentRequest::query()
            ->with('requester')
            ->whereNull('fulfilled_at')
            ->whereIn('employee_id', Employee::where('user_id', $user->id)->select('id'))
            ->orderBy('report_date')
            ->get();
    }

    /**
     * Everybody on the open projects this person manages: the members of the teams on
     * them, and whoever holds an open task in them.
     *
     * @return list<int>
     */
    public static function peopleOnProjectsManagedBy(int $employeeId): array
    {
        return Project::query()
            ->open()
            ->where('manager_id', $employeeId)
            ->with(['teams.members', 'tasks' => fn ($tasks) => $tasks->open()->with('assignees')])
            ->get()
            ->flatMap(fn (Project $project) => $project->teams
                ->flatMap(fn ($team) => $team->members->pluck('id'))
                ->merge($project->tasks->flatMap(fn (Task $task) => $task->assignees->pluck('id'))))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The request for this person and day, filed or not.
     */
    public static function forDay(int $employeeId, string $date): ?EodUrgentRequest
    {
        if (! self::isReady()) {
            return null;
        }

        return EodUrgentRequest::query()
            ->with('requester')
            ->where('employee_id', $employeeId)
            ->whereDate('report_date', $date)
            ->first();
    }

    /**
     * Every request for a day, by the person it is for.
     *
     * @return Collection<int, EodUrgentRequest>
     */
    public static function onDay(string $date): Collection
    {
        if (! self::isReady()) {
            return collect();
        }

        return EodUrgentRequest::query()
            ->with('requester')
            ->whereDate('report_date', $date)
            ->get()
            ->keyBy('employee_id');
    }
}
