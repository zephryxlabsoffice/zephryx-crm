<?php

namespace App\Http\Controllers;

use App\Support\EmployeeDirectory;
use App\Support\EmployeePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Employees — the spine every other module references (foundation spec §12).
 *
 * Reads the `employees` table through App\Support\EmployeeDirectory, which owns
 * the row shape the views expect. Filtering and pagination happen in SQL: the
 * page this replaced loaded every employee and filtered the collection in PHP,
 * which is survivable at twelve people and a full table scan per keystroke at
 * any size worth having a search box for.
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

        $query = EmployeeDirectory::query(
            $search !== '' ? $search : null,
            $status,
            $department,
        );

        return response()->view('employees.index', [
            'activeNav' => 'employees',
            'employees' => EmployeeDirectory::paginate($query, self::PER_PAGE),
            'search' => $search,
            'status' => $status,
            'department' => $department,
            'filtered' => $search !== '' || $status !== null || $department !== null,
            'departments' => EmployeeDirectory::departmentsInUse(),
            'stats' => EmployeeDirectory::stats(),
            'breakdown' => EmployeeDirectory::byDepartment(),
            'circumference' => 2 * M_PI * self::DONUT_RADIUS,
            'donutRadius' => self::DONUT_RADIUS,
            'starters' => EmployeeDirectory::recentStarters(),
            'birthdays' => EmployeeDirectory::birthdays(),
        ]);
    }
}
