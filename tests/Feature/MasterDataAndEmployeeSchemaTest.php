<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\User;
use App\Support\LeavePolicy;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The first two tables of the backend phase: master data, and the employment
 * record behind a staff account.
 */
class MasterDataAndEmployeeSchemaTest extends TestCase
{
    use RefreshDatabase;

    /* ══════════════════════════════════════════════════════════════════════
       MASTER DATA — SEEDED IN PRODUCTION, BECAUSE THE APP NEEDS IT TO RUN
       ══════════════════════════════════════════════════════════════════════ */

    /*
     * Note there is no seeding in these tests. Tests\TestCase seeds
     * DatabaseSeeder for every test, so the master data lists are already
     * present — which is itself the thing being asserted first.
     */

    public function test_the_four_lists_are_seeded(): void
    {
        foreach (MasterDataItem::lists() as $list) {
            $this->assertGreaterThan(
                0,
                MasterDataItem::inList($list)->count(),
                "{$list} seeded nothing, and a module that reads it has no options to offer",
            );
        }
    }

    public function test_leave_types_come_from_the_policy_rather_than_a_second_list(): void
    {
        /*
         * LeavePolicy already decides which types exist. A hand-written list in
         * the seeder would be a second source of truth for the same fact, and
         * the day they disagreed a request would be filed against a type no
         * balance could be computed for.
         */
        $seeded = MasterDataItem::inList(MasterDataItem::LEAVE_TYPES)->pluck('name')->sort()->values();
        $policy = collect(LeavePolicy::types())->pluck('label')->sort()->values();

        $this->assertSame($policy->all(), $seeded->all());
    }

    public function test_running_the_seeder_again_does_not_undo_a_rename(): void
    {
        /*
         * The seeder runs on every deploy. If it wrote names each time, an
         * administrator who renamed a department would find it back the way it
         * was, silently, with nothing on screen to explain why.
         */
        $department = MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->first();
        $department->update(['name' => 'Renamed By A Human']);

        (new MasterDataSeeder)->run();

        $this->assertSame('Renamed By A Human', $department->fresh()->name);
    }

    public function test_a_code_may_repeat_across_lists_but_not_within_one(): void
    {
        // 'QA' as a department and 'QA' as a designation are different things
        // that may legitimately coexist.
        MasterDataItem::create(['list' => MasterDataItem::DEPARTMENTS, 'name' => 'Quality', 'code' => 'QA']);
        MasterDataItem::create(['list' => MasterDataItem::DESIGNATIONS, 'name' => 'QA Engineer', 'code' => 'QA']);

        $this->assertSame(2, MasterDataItem::where('code', 'QA')->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        MasterDataItem::create(['list' => MasterDataItem::DEPARTMENTS, 'name' => 'Quality Assurance', 'code' => 'QA']);
    }

    public function test_in_use_counts_the_records_that_would_be_orphaned(): void
    {
        // The admin screen states this number before offering to deactivate, so
        // the question is answered before it is asked. It has to be real.
        $department = MasterDataItem::create([
            'list' => MasterDataItem::DEPARTMENTS, 'name' => 'Research', 'code' => 'RSCH',
        ]);

        $this->assertSame(0, $department->inUse());

        $this->employee(['department_id' => $department->id]);

        $this->assertSame(1, $department->inUse());
    }

    public function test_in_use_is_null_rather_than_zero_for_a_list_it_cannot_count(): void
    {
        /*
         * Zero would read as "nobody is using this, safe to deactivate", which
         * is a different and possibly wrong statement from "this module does
         * not exist yet, so I cannot tell you".
         */
        $type = MasterDataItem::inList(MasterDataItem::DOCUMENT_TYPES)->firstOrFail();

        $this->assertNull($type->inUse());
    }

    /* ══════════════════════════════════════════════════════════════════════
       EMPLOYEES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_employment_record_resolves_its_person_and_its_master_data(): void
    {
        // The seeded rows, not invented ones — this is the shape a real
        // employee gets when somebody picks from the form's dropdowns.
        $department = MasterDataItem::inList(MasterDataItem::DEPARTMENTS)
            ->where('name', 'Development')->firstOrFail();
        $designation = MasterDataItem::inList(MasterDataItem::DESIGNATIONS)
            ->where('name', 'Backend Developer')->firstOrFail();

        $employee = $this->employee([
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $this->assertSame('Development', $employee->department->name);
        $this->assertSame('Backend Developer', $employee->designation->name);
        $this->assertSame('staff', $employee->user->account_type);
    }

    public function test_the_directory_counts_only_accounts_that_may_sign_in(): void
    {
        $this->employee([], ['status' => 'active']);
        $this->employee([], ['status' => 'active']);
        $this->employee([], ['status' => 'inactive']);

        $this->assertSame(3, Employee::count());
        $this->assertSame(2, Employee::active()->count());
    }

    public function test_new_this_month_is_a_query_rather_than_a_string_comparison(): void
    {
        $this->employee(['joined_on' => Carbon::now()->startOfMonth()]);
        $this->employee(['joined_on' => Carbon::now()->endOfMonth()]);
        $this->employee(['joined_on' => Carbon::now()->subMonth()]);

        $this->assertSame(2, Employee::joinedIn(Carbon::now())->count());
    }

    public function test_a_person_cannot_have_two_employment_records(): void
    {
        // One employment per account. Two would mean two departments, and every
        // count in the application would double for that person.
        $employee = $this->employee();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Employee::create(['user_id' => $employee->user_id]);
    }

    public function test_the_demo_employment_records_are_local_only(): void
    {
        // The accounts are seeded from the same rows; production creates real
        // employees through the module, never from a fixture.
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        // Called directly rather than through $this->seed(), which prompts for
        // confirmation outside local and would hang on the answer.
        (new EmployeeSeeder)->run();

        $this->assertSame(0, Employee::count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $account
     */
    protected function employee(array $attributes = [], array $account = []): Employee
    {
        static $n = 0;
        $n++;

        // The overrides go on the LEFT of `+`: the left operand wins, and
        // writing it the other way round silently discards every override.
        $user = User::create($account + [
            'user_id' => 'EMP'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'name' => 'Person '.$n,
            'email' => "person{$n}@example.test",
            'password' => 'a-password-of-sufficient-length',
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        return Employee::create($attributes + [
            'user_id' => $user->id,
            'joined_on' => Carbon::now()->subYear(),
        ]);
    }
}
