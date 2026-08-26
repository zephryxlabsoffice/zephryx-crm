<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoEmployees;
use App\Support\EmployeePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Employees — the spine every other module references (foundation spec §12).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY. There is no `users` table yet; rows come from
 * App\Support\Demo\DemoEmployees, which returns nothing outside local + debug.
 *
 * Still to add with the backend: the `employees.view` permission check, and
 * §2.6's rules — a person may not edit their own HR-controlled fields, and may
 * not act on anyone who outranks them in the `people` domain.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EmployeeController extends Controller
{
    protected const PER_PAGE = 8;

    /** The donut's radius and the circumference derived from it. */
    protected const DONUT_RADIUS = 57;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(EmployeePresenter::statusOptions())],
            'department' => ['nullable', 'string', 'max:60'],
        ]);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;
        $department = $filters['department'] ?? null;

        $matches = DemoEmployees::all()
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $row) => str_contains(
                    mb_strtolower($row['name'].' '.$row['user_id'].' '.$row['designation'].' '.$row['email']),
                    mb_strtolower($search)
                )
            ))
            ->when($status, fn ($rows) => $rows->where('status', $status))
            ->when($department, fn ($rows) => $rows->where('department', $department))
            ->values();

        return response()->view('employees.index', [
            'activeNav' => 'employees',
            'employees' => $this->paginate($matches, $request),
            'search' => $search,
            'status' => $status,
            'department' => $department,
            'filtered' => $search !== '' || $status !== null || $department !== null,
            'departments' => DemoEmployees::all()->pluck('department')->unique()->sort()->values()->all(),
            'stats' => DemoEmployees::stats(),
            'breakdown' => DemoEmployees::byDepartment(),
            'circumference' => 2 * M_PI * self::DONUT_RADIUS,
            'donutRadius' => self::DONUT_RADIUS,
            'starters' => DemoEmployees::recentStarters(),
            'birthdays' => DemoEmployees::birthdays(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate($rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, self::PER_PAGE)->values(),
            total: $rows->count(),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
