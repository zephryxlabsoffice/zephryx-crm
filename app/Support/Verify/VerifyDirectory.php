<?php

namespace App\Support\Verify;

use App\Models\Employee;
use App\Support\Auth\SecurityLock;
use App\Support\EmployeePresenter;
use App\Support\Realm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What verify.zephryxlabs.in reads from the CRM (2026-10-29).
 *
 * Read straight from the database by the same application - there is no API
 * between the two sites, so there is nothing to call and nothing to leak.
 * Only employment records count: a mentor, a normal account (hr@) or a client
 * has no Employee row and is "not found".
 *
 * What a page shows depends on whether the person works here now:
 *
 * - WORKING (active, or on a security hold - still employed): photo, name,
 *   designation, department, employment type.
 * - LEFT: their name and the day they left. Nothing else, so an old card
 *   cannot pass for a current one and little is said about people who have
 *   gone (owner, 2026-10-29).
 * - An intern's EARLIER ID, after conversion to full-time: they still work
 *   here, under the new ID, which the page names.
 */
class VerifyDirectory
{
    public const WORKING = 'working';

    public const LEFT = 'left';

    /** Settings, in company_settings (outside the settings catalogue). */
    public const PHONE = 'verify.phone';

    public const EMAIL = 'verify.email';

    /** Where registration documents live, on the private disk. */
    public const REGISTRATION_DIR = 'verify/registrations';

    public static function find(string $employeeId): ?Employee
    {
        return Employee::query()
            ->with(['user', 'designation', 'department', 'profile', 'convertedTo.user'])
            ->whereHas('user', fn ($q) => $q
                ->where('user_id', strtoupper(trim($employeeId)))
                ->where('account_type', Realm::STAFF))
            ->first();
    }

    /**
     * The page, as data.
     *
     * @return array<string, mixed>
     */
    public static function card(Employee $employee): array
    {
        $user = $employee->user;

        // Converted from intern: this ID is retired, the person is not.
        $successor = $employee->convertedTo;

        if ($successor !== null && self::isWorking($successor)) {
            return self::working($successor) + ['earlier_id' => $user->user_id];
        }

        if (self::isWorking($employee)) {
            return self::working($employee) + ['earlier_id' => null];
        }

        return [
            'state' => self::LEFT,
            'id' => $user->user_id,
            'name' => $user->name,
            'left_on' => $employee->left_on,
            'earlier_id' => null,
        ];
    }

    public static function isWorking(Employee $employee): bool
    {
        return in_array($employee->user?->status, ['active', SecurityLock::STATUS], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function working(Employee $employee): array
    {
        return [
            'state' => self::WORKING,
            'employee' => $employee,
            'id' => $employee->user->user_id,
            'name' => $employee->user->name,
            'designation' => $employee->designation?->name,
            'department' => $employee->department?->name,
            'type' => EmployeePresenter::employmentType($employee->employment_type)['label'] ?? null,
            'joined_on' => $employee->joined_on,
            'has_photo' => $employee->profile?->photo_path !== null,
        ];
    }

    public static function recordScan(Employee $employee, string $source, Request $request): void
    {
        DB::table('verify_scans')->insert([
            'employee_id' => $employee->id,
            'source' => $source,
            'ip_hash' => $request->ip() === null ? null : hash_hmac('sha256', $request->ip(), (string) config('app.key')),
            'created_at' => now(),
        ]);
    }

    /**
     * The dashboard's figures.
     *
     * @return array<string, int>
     */
    public static function stats(): array
    {
        $working = Employee::query()->with('user')->get()->filter(fn (Employee $e) => self::isWorking($e));
        $since = Carbon::now()->subDays(30);

        return [
            'working' => $working->count(),
            'full_time' => $working->where('employment_type', Employee::FULL_TIME)->count(),
            'interns' => $working->where('employment_type', Employee::INTERN)->count(),
            'freelancers' => $working->where('employment_type', Employee::FREELANCE)->count(),
            'qr_scans' => DB::table('verify_scans')->where('source', 'qr')->count(),
            'qr_scans_30' => DB::table('verify_scans')->where('source', 'qr')->where('created_at', '>=', $since)->count(),
            'searches' => DB::table('verify_scans')->where('source', 'search')->count(),
        ];
    }

    /**
     * Everybody with an employment record, working first, for the dashboard.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(?string $search = null): Collection
    {
        $scans = DB::table('verify_scans')
            ->select('employee_id', DB::raw("SUM(CASE WHEN source = 'qr' THEN 1 ELSE 0 END) as qr"), DB::raw('COUNT(*) as views'))
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $search = trim((string) $search);

        return Employee::query()
            ->with(['user', 'designation', 'department'])
            ->whereHas('user', fn ($q) => $q->where('account_type', Realm::STAFF))
            ->get()
            ->filter(fn (Employee $e) => $search === ''
                || str_contains(strtolower($e->user->name.' '.$e->user->user_id), strtolower($search)))
            ->map(fn (Employee $e) => [
                'id' => $e->user->user_id,
                'name' => $e->user->name,
                'type' => EmployeePresenter::employmentType($e->employment_type)['label'] ?? $e->employment_type,
                'designation' => $e->designation?->name,
                'working' => self::isWorking($e),
                'left_on' => $e->left_on,
                'qr' => (int) ($scans[$e->id]->qr ?? 0),
                'views' => (int) ($scans[$e->id]->views ?? 0),
            ])
            ->sortBy([['working', 'desc'], ['name', 'asc']])
            ->values();
    }

    public static function setting(string $key): ?string
    {
        $value = DB::table('company_settings')->where('key', $key)->value('value');

        return $value === null ? null : json_decode($value, true);
    }

    public static function putSetting(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            DB::table('company_settings')->where('key', $key)->delete();

            return;
        }

        DB::table('company_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
