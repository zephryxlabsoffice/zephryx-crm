<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\MasterDataItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The employee directory, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY THE ROWS ARE ARRAYS AND NOT MODELS
 *
 * This returns the same row shape DemoEmployees did — name, user_id,
 * department, designation, email, status, joined, dob, announce_milestones —
 * because the views and App\Support\Milestones already read it and are already
 * tested against it. Handing them models instead would have meant editing every
 * template in the module to prove nothing, and turning a data-source change
 * into a rewrite that could break the rendering it was supposed to preserve.
 *
 * The shape is a contract between this class and the views, and it is the one
 * place the two are joined. When a template needs something new it is added
 * here, not fetched in the view.
 *
 * WHERE `status` COMES FROM, AND WHERE `on_leave` WENT
 *
 * `status` is the ACCOUNT's status — active, inactive, suspended — because that
 * is what decides whether somebody can sign in and whether they still work
 * here. The demo rows also carried `on_leave`, which is not a stored state at
 * all but a question about today that an approved leave request answers. It
 * returns when the Leave module has a table to ask; until then this never
 * reports it, rather than reporting a stale copy of it.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmployeeDirectory
{
    /**
     * The directory query, filtered.
     *
     * Filtering happens in SQL rather than over a loaded collection: the
     * handover's page loaded every employee and filtered in PHP, which is
     * survivable at twelve people and is a full table scan per keystroke at any
     * size worth having a search box for.
     *
     * @return Builder<Employee>
     */
    public static function query(?string $search = null, ?string $status = null, ?string $department = null): Builder
    {
        return Employee::query()
            ->with(['user', 'department', 'designation'])
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->select('employees.*')
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('users.name', 'like', $like)
                    ->orWhere('users.user_id', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhereHas('designation', fn (Builder $d) => $d->where('name', 'like', $like));
            }))
            ->when($status, fn (Builder $q, string $value) => $q->where('users.status', $value))
            ->when($department, fn (Builder $q, string $name) => $q->whereHas(
                'department',
                fn (Builder $d) => $d->where('name', $name)
            ))
            /*
             * By staff ID, which is the order the page has always been in.
             * Sorting by name would arguably read better and is a decision
             * about the page, not about where its rows come from — making it
             * here would have smuggled a UI change into a data-source swap.
             * Raised for the review round instead.
             */
            ->orderBy('users.user_id');
    }

    /**
     * One row in the shape the views and Milestones read.
     *
     * @return array<string, mixed>
     */
    public static function row(Employee $employee): array
    {
        return [
            /*
             * The employment record's own key, for the places that write
             * against it — team membership is one. `user_id` is the staff ID
             * people read and quote; this is what a form posts back, and the
             * two are deliberately not the same value.
             */
            'employee_id' => $employee->id,
            'user_id' => $employee->user?->user_id,
            'name' => $employee->user?->name,
            'email' => $employee->user?->email,
            'status' => $employee->user?->status,
            'department' => $employee->department?->name,
            'designation' => $employee->designation?->name,
            'joined' => $employee->joined_on?->toDateString(),
            'dob' => $employee->date_of_birth?->toDateString(),
            'announce_milestones' => $employee->announce_milestones,
        ];
    }

    /**
     * Every employee, as rows.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        return self::query()->get()->map(fn (Employee $e) => self::row($e));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        $page = $query->paginate($perPage)->withQueryString();

        return $page->through(fn (Employee $e) => self::row($e));
    }

    /**
     * The headline counts.
     *
     * @return array<string, int>
     */
    public static function stats(): array
    {
        return [
            'total' => Employee::count(),
            'active' => Employee::active()->count(),
            // Derived from Leave once that module has a table. Reported as zero
            // rather than guessed at — see the note at the top of this class.
            'on_leave' => 0,
            'new_this_month' => Employee::joinedIn(Carbon::now())->count(),
        ];
    }

    /**
     * Headcount per department, largest first — the donut's data.
     *
     * Grouped in SQL so the total under the chart and the segments in it come
     * from one query and cannot disagree.
     *
     * @return list<array{name: string, count: int, share: float}>
     */
    public static function byDepartment(): array
    {
        $total = Employee::count();

        if ($total === 0) {
            return [];
        }

        return Employee::query()
            ->join('master_data_items', 'master_data_items.id', '=', 'employees.department_id')
            ->groupBy('master_data_items.name')
            ->orderByDesc('headcount')
            ->orderBy('master_data_items.name')
            ->selectRaw('master_data_items.name as name, count(*) as headcount')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'count' => (int) $row->headcount,
                'share' => round($row->headcount / $total * 100, 1),
            ])
            ->all();
    }

    /**
     * The departments a filter may offer — the ones somebody is actually in.
     *
     * Not every active department: a filter that returns nothing is a dead end
     * somebody has to discover by trying it.
     *
     * @return list<string>
     */
    public static function departmentsInUse(): array
    {
        return MasterDataItem::query()
            ->whereIn('id', Employee::query()->whereNotNull('department_id')->select('department_id'))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * The most recent starters.
     *
     * @return list<array<string, string>>
     */
    public static function recentStarters(int $limit = 3): array
    {
        return Employee::query()
            ->with(['user', 'designation'])
            ->whereNotNull('joined_on')
            ->orderByDesc('joined_on')
            ->limit($limit)
            ->get()
            ->map(fn (Employee $e) => [
                'name' => (string) $e->user?->name,
                'designation' => (string) $e->designation?->name,
                'when' => $e->joined_on->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Upcoming birthdays — day and month only, never the year (see Milestones).
     *
     * @return list<array<string, string>>
     */
    public static function birthdays(int $limit = 4): array
    {
        return array_slice(
            array_map(
                fn (array $m) => [
                    'name' => $m['employee']['name'],
                    'date' => $m['day_month'],
                    'countdown' => $m['label'],
                ],
                Milestones::upcoming(Milestones::BIRTHDAY)
            ),
            0,
            $limit
        );
    }
}
