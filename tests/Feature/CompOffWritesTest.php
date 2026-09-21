<?php

namespace Tests\Feature;

use App\Models\CompOff;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\SundayAgainstLeaveRequest;
use App\Models\SundayRoster;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sunday/holiday rostering, comp-off, and Sunday-against-leave (review
 * round, Attendance: comp-off, decided 2026-09-11).
 *
 * Every date in this file is pinned to 2026-08-23, a Sunday, so "rostering
 * an upcoming Sunday" and "checking in today" line up without depending on
 * whatever day the suite happens to run on.
 */
class CompOffWritesTest extends TestCase
{
    /** A Sunday. */
    protected const SUNDAY = '2026-08-23';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::SUNDAY)->setTime(9, 30));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ROSTERING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_manager_may_roster_anyone(): void
    {
        $this->signInAsEmployee(['employee', 'manager']);
        $worker = $this->anEmployee('EMP810', 'A Worker');

        $this->post('/attendance/roster', [
            'employee_id' => $worker->id,
            'date' => self::SUNDAY,
        ])->assertRedirect();

        $this->assertSame(1, SundayRoster::where('employee_id', $worker->id)->count());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ATTENDANCE_ROSTERED)->count());
    }

    public function test_a_team_lead_may_roster_only_their_own_team(): void
    {
        $lead = $this->signInAsEmployee(['employee', 'team_lead']);
        $team = $this->aTeam(['lead_id' => $lead->id]);
        $mine = $this->anEmployee('EMP811', 'On My Team');
        $team->members()->attach($mine->id);

        $notMine = $this->anEmployee('EMP812', 'Somebody Else');

        $this->post('/attendance/roster', [
            'employee_id' => $mine->id,
            'date' => self::SUNDAY,
        ])->assertRedirect();
        $this->assertSame(1, SundayRoster::where('employee_id', $mine->id)->count());

        $this->post('/attendance/roster', [
            'employee_id' => $notMine->id,
            'date' => self::SUNDAY,
        ])->assertForbidden();
        $this->assertSame(0, SundayRoster::where('employee_id', $notMine->id)->count());
    }

    public function test_a_working_day_cannot_be_rostered(): void
    {
        $this->signInAsEmployee(['employee', 'manager']);
        $worker = $this->anEmployee('EMP813', 'A Worker');

        // The Monday after the pinned Sunday — an ordinary working day.
        $this->post('/attendance/roster', [
            'employee_id' => $worker->id,
            'date' => Carbon::parse(self::SUNDAY)->addDay()->toDateString(),
        ])->assertSessionHasErrors('date');

        $this->assertSame(0, SundayRoster::count());
    }

    public function test_a_freelancer_cannot_be_rostered(): void
    {
        $this->signInAsEmployee(['employee', 'manager']);
        $freelancer = $this->anEmployee('EMP814', 'A Freelancer');
        $freelancer->update(['employment_type' => Employee::FREELANCE]);

        $this->post('/attendance/roster', [
            'employee_id' => $freelancer->id,
            'date' => self::SUNDAY,
        ])->assertSessionHasErrors('employee_id');

        $this->assertSame(0, SundayRoster::count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       EARNING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_checking_out_a_full_rostered_day_earns_a_comp_off(): void
    {
        $employee = $this->signInAsEmployee();
        SundayRoster::create(['employee_id' => $employee->id, 'date' => self::SUNDAY]);

        $this->post('/attendance/check-in')->assertRedirect();

        Carbon::setTestNow(Carbon::parse(self::SUNDAY)->setTime(18, 30));
        $this->post('/attendance/check-out')->assertRedirect();

        $compOff = CompOff::where('employee_id', $employee->id)->first();

        $this->assertNotNull($compOff);
        $this->assertSame(self::SUNDAY, $compOff->earned_on->toDateString());
        $this->assertSame('2026-08-30', $compOff->expires_on->toDateString());
        $this->assertSame(CompOff::AVAILABLE, $compOff->status);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::COMPOFF_EARNED)->count());
    }

    public function test_a_half_day_on_a_rostered_sunday_earns_nothing(): void
    {
        $employee = $this->signInAsEmployee();
        SundayRoster::create(['employee_id' => $employee->id, 'date' => self::SUNDAY]);

        $this->post('/attendance/check-in')->assertRedirect();

        // Half an hour later — well under the half-day threshold.
        Carbon::setTestNow(Carbon::parse(self::SUNDAY)->setTime(10, 0));
        $this->post('/attendance/check-out')->assertRedirect();

        $this->assertSame(0, CompOff::where('employee_id', $employee->id)->count());
    }

    public function test_an_unrostered_sunday_earns_no_comp_off(): void
    {
        $employee = $this->signInAsEmployee();

        $this->post('/attendance/check-in')->assertRedirect();

        Carbon::setTestNow(Carbon::parse(self::SUNDAY)->setTime(18, 30));
        $this->post('/attendance/check-out')->assertRedirect();

        $this->assertSame(0, CompOff::where('employee_id', $employee->id)->count());
    }

    public function test_checking_out_twice_does_not_earn_it_twice(): void
    {
        $employee = $this->signInAsEmployee();
        SundayRoster::create(['employee_id' => $employee->id, 'date' => self::SUNDAY]);

        $this->post('/attendance/check-in');
        Carbon::setTestNow(Carbon::parse(self::SUNDAY)->setTime(18, 30));
        $this->post('/attendance/check-out');
        $this->post('/attendance/check-out'); // idempotent — does not move anything

        $this->assertSame(1, CompOff::where('employee_id', $employee->id)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       TAKING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_requesting_to_take_a_comp_off_needs_a_managers_approval(): void
    {
        $employee = $this->signInAsEmployee();
        $lead = $this->anEmployee('EMP815', 'The Lead');
        $team = $this->aTeam(['lead_id' => $lead->id]);
        $team->members()->attach($employee->id);

        $compOff = $this->aCompOff($employee);

        $this->post('/comp-offs/'.$compOff->id.'/take', [
            'take_date' => Carbon::parse(self::SUNDAY)->addDay()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(CompOff::PENDING, $compOff->fresh()->status);

        $this->actingAs($lead->user);
        $lead->user->roles()->sync(Role::where('role_key', 'team_lead')->pluck('id'));
        app(Rbac::class)->forget($lead->user);

        $this->post('/comp-offs/'.$compOff->id.'/approve')->assertRedirect();

        $this->assertSame(CompOff::TAKEN, $compOff->fresh()->status);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::COMPOFF_TAKEN)->count());
    }

    public function test_rejecting_a_take_request_returns_it_to_available(): void
    {
        $employee = $this->signInAsEmployee();
        $compOff = $this->aCompOff($employee);
        $compOff->update([
            'status' => CompOff::PENDING,
            'take_date' => Carbon::parse(self::SUNDAY)->addDay(),
            'requested_at' => now(),
        ]);

        $this->signInAsEmployee(['employee', 'manager'], 'EMP816');

        $this->post('/comp-offs/'.$compOff->id.'/reject', ['note' => 'Too much on that week.'])
            ->assertRedirect();

        $compOff->refresh();

        $this->assertSame(CompOff::AVAILABLE, $compOff->status);
        $this->assertNull($compOff->take_date);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::COMPOFF_TAKE_REJECTED)->count());
    }

    public function test_a_lapsed_comp_off_cannot_be_requested(): void
    {
        $employee = $this->signInAsEmployee();
        $compOff = CompOff::create([
            'employee_id' => $employee->id,
            'earned_on' => Carbon::parse(self::SUNDAY)->subWeeks(3),
            'expires_on' => Carbon::parse(self::SUNDAY)->subWeeks(2),
            'status' => CompOff::AVAILABLE,
        ]);

        $this->post('/comp-offs/'.$compOff->id.'/take', [
            'take_date' => Carbon::parse(self::SUNDAY)->addDay()->toDateString(),
        ])->assertSessionHasErrors('take_date');

        $this->assertSame(CompOff::AVAILABLE, $compOff->fresh()->status);
    }

    public function test_somebody_cannot_request_another_persons_comp_off(): void
    {
        $owner = $this->anEmployee('EMP817', 'The Owner');
        $compOff = $this->aCompOff($owner);

        $this->signInAsEmployee(['employee'], 'EMP818');

        $this->post('/comp-offs/'.$compOff->id.'/take', [
            'take_date' => Carbon::parse(self::SUNDAY)->addDay()->toDateString(),
        ])->assertNotFound();
    }

    /* ══════════════════════════════════════════════════════════════════════
       SUNDAY AGAINST LEAVE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_approving_sunday_against_leave_returns_a_day_and_rosters_the_date(): void
    {
        $employee = $this->signInAsEmployee();

        $leave = LeaveRequest::create([
            'reference' => 'LV-2026-800',
            'employee_id' => $employee->id,
            'type' => 'casual',
            'from_date' => self::SUNDAY,
            'to_date' => Carbon::parse(self::SUNDAY)->addDays(2)->toDateString(),
            'days' => 3,
            'reason' => 'Family event.',
            'status' => 'approved',
            'applied_at' => now(),
        ]);

        $this->post('/leave/'.$leave->reference.'/sunday-against-leave', [
            'date' => self::SUNDAY,
        ])->assertRedirect();

        $sundayRequest = SundayAgainstLeaveRequest::where('leave_request_id', $leave->id)->firstOrFail();
        $this->assertSame(SundayAgainstLeaveRequest::PENDING, $sundayRequest->status);

        $this->signInAsEmployee(['employee', 'manager'], 'EMP819');

        $this->post('/sunday-against-leave/'.$sundayRequest->id.'/approve')->assertRedirect();

        $this->assertSame(2.0, (float) $leave->fresh()->days);
        $this->assertSame(1, SundayRoster::where('employee_id', $employee->id)
            ->whereDate('date', self::SUNDAY)->count());
        $this->assertSame(
            1,
            DB::table('audit_log')->where('action', AuditLog::SUNDAY_AGAINST_LEAVE_APPROVED)->count(),
        );
    }

    public function test_sunday_against_leave_must_be_inside_the_leave_range(): void
    {
        $employee = $this->signInAsEmployee();

        $leave = LeaveRequest::create([
            'reference' => 'LV-2026-801',
            'employee_id' => $employee->id,
            'type' => 'casual',
            'from_date' => Carbon::parse(self::SUNDAY)->addDays(3)->toDateString(),
            'to_date' => Carbon::parse(self::SUNDAY)->addDays(5)->toDateString(),
            'days' => 3,
            'reason' => 'Family event.',
            'status' => 'approved',
            'applied_at' => now(),
        ]);

        $this->post('/leave/'.$leave->reference.'/sunday-against-leave', [
            'date' => self::SUNDAY,
        ])->assertSessionHasErrors('date');

        $this->assertSame(0, SundayAgainstLeaveRequest::count());
    }

    public function test_sunday_against_leave_must_be_asked_in_the_same_month(): void
    {
        $employee = $this->signInAsEmployee();

        // A leave request spanning into next month, asked about from this one.
        $leave = LeaveRequest::create([
            'reference' => 'LV-2026-802',
            'employee_id' => $employee->id,
            'type' => 'casual',
            'from_date' => self::SUNDAY,
            'to_date' => Carbon::parse(self::SUNDAY)->addMonth()->toDateString(),
            'days' => 5,
            'reason' => 'Long trip.',
            'status' => 'approved',
            'applied_at' => now(),
        ]);

        $nextMonthSunday = Carbon::parse(self::SUNDAY)->addMonth()->toDateString();

        $this->post('/leave/'.$leave->reference.'/sunday-against-leave', [
            'date' => $nextMonthSunday,
        ])->assertSessionHasErrors('date');

        $this->assertSame(0, SundayAgainstLeaveRequest::count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    protected function aCompOff(Employee $employee): CompOff
    {
        return CompOff::create([
            'employee_id' => $employee->id,
            'earned_on' => self::SUNDAY,
            'expires_on' => Carbon::parse(self::SUNDAY)->addWeek(),
            'status' => CompOff::AVAILABLE,
        ]);
    }

    /**
     * @param  list<string>  $roles
     */
    protected function signInAsEmployee(array $roles = ['employee'], string $staffId = 'EMP800'): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    protected function anEmployee(string $staffId, string $name): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function aTeam(array $attributes = []): Team
    {
        return Team::create($attributes + [
            'reference' => 'TM-18'.fake()->unique()->numberBetween(10, 99),
            'name' => 'A Team '.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'active',
        ]);
    }
}
