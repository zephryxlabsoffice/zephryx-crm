<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter as P;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Back-dated attendance (decided 2026-10-27): the "attendance starts on" date,
 * and HR filling in a missed past day.
 */
class AttendanceBackfillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday, so the days around it are ordinary working days.
        Carbon::setTestNow(Carbon::parse('2026-10-28 11:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ATTENDANCE STARTS ON
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_missed_day_before_the_start_date_is_not_tracked_rather_than_absent(): void
    {
        config(['attendance.starts_on' => '2026-10-14']);

        $this->assertSame(P::NOT_TRACKED, AttendancePolicy::evaluate('2026-10-13', null)['state']);
        $this->assertSame(P::ABSENT, AttendancePolicy::evaluate('2026-10-15', null)['state']);
    }

    public function test_a_record_before_the_start_date_still_counts(): void
    {
        config(['attendance.starts_on' => '2026-10-14']);

        $state = AttendancePolicy::evaluate('2026-10-13', [
            'date' => '2026-10-13', 'check_in' => '09:30', 'check_out' => '18:30',
        ])['state'];

        $this->assertSame(P::PRESENT, $state);
    }

    public function test_without_a_start_date_nothing_changes(): void
    {
        config(['attendance.starts_on' => null]);

        $this->assertSame(P::ABSENT, AttendancePolicy::evaluate('2026-10-13', null)['state']);
    }

    public function test_working_days_in_the_month_begin_at_the_start_date(): void
    {
        $month = Carbon::parse('2026-10-01');

        config(['attendance.starts_on' => null]);
        $all = AttendancePolicy::workingDaysInMonth($month);

        config(['attendance.starts_on' => '2026-10-14']);
        $fromStart = AttendancePolicy::workingDaysInMonth($month);

        // 1–13 October 2026 holds two Sundays (4th, 11th), so eleven working days drop out.
        $this->assertSame($all - 11, $fromStart);
    }

    public function test_the_start_date_can_be_set_from_the_admin_panel(): void
    {
        $this->signInAsAdmin();

        $this->post('/admin/settings', ['key' => 'attendance.starts_on', 'value' => '2026-10-14'])
            ->assertRedirect('/admin/settings');

        $this->assertSame('2026-10-14', config('attendance.starts_on'));
        $this->assertSame('"2026-10-14"', DB::table('company_settings')->where('key', 'attendance.starts_on')->value('value'));
    }

    public function test_a_malformed_start_date_is_refused(): void
    {
        $this->signInAsAdmin();

        $this->post('/admin/settings', ['key' => 'attendance.starts_on', 'value' => 'last tuesday'])
            ->assertSessionHasErrors('value');

        $this->assertNull(config('attendance.starts_on'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       HR ENTERS A MISSED DAY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_can_enter_a_missed_past_day_and_it_is_audited(): void
    {
        $this->signIn(['employee', 'hr'], 'EMP900');
        $riya = $this->anEmployee('EMP901', 'Riya Sharma');

        $this->post('/attendance/add', [
            'employee_id' => $riya->id,
            'date' => '2026-10-27',
            'check_in' => '09:30',
            'check_out' => '18:30',
            'reason' => 'Worked on site at the client office.',
        ])->assertRedirect('/attendance/ATT-2026-10-27-EMP901');

        $record = AttendanceRecord::where('employee_id', $riya->id)->firstOrFail();
        $this->assertSame('09:30', Carbon::parse($record->check_in)->format('H:i'));
        $this->assertSame('18:30', Carbon::parse($record->check_out)->format('H:i'));

        $entry = DB::table('audit_log')->where('action', AuditLog::ATTENDANCE_ENTERED)->first();
        $this->assertSame('ATT-2026-10-27-EMP901', $entry->entity_id);
        $this->assertStringContainsString('client office', $entry->after_json);

        $this->get('/attendance/ATT-2026-10-27-EMP901')
            ->assertOk()
            ->assertSee('Worked on site at the client office.');
    }

    public function test_an_existing_record_is_never_overwritten(): void
    {
        $this->signIn(['employee', 'hr'], 'EMP900');
        $riya = $this->anEmployee('EMP901', 'Riya Sharma');

        AttendanceRecord::create([
            'employee_id' => $riya->id, 'date' => '2026-10-27', 'check_in' => '10:05',
        ]);

        $this->post('/attendance/add', [
            'employee_id' => $riya->id,
            'date' => '2026-10-27',
            'check_in' => '09:30',
            'check_out' => '18:30',
            'reason' => 'Trying to close an open day.',
        ])->assertSessionHasErrors('date');

        $record = AttendanceRecord::where('employee_id', $riya->id)->firstOrFail();
        $this->assertSame('10:05', Carbon::parse($record->check_in)->format('H:i'));
        $this->assertNull($record->check_out);
    }

    public function test_today_and_the_future_cannot_be_entered(): void
    {
        $this->signIn(['employee', 'hr'], 'EMP900');
        $riya = $this->anEmployee('EMP901', 'Riya Sharma');

        foreach (['2026-10-28', '2026-10-29'] as $date) {
            $this->post('/attendance/add', [
                'employee_id' => $riya->id,
                'date' => $date,
                'check_in' => '09:30',
                'check_out' => '18:30',
                'reason' => 'Should not be possible.',
            ])->assertSessionHasErrors('date');
        }

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_nobody_enters_their_own_day(): void
    {
        $me = $this->signIn(['employee', 'hr'], 'EMP900');

        $this->post('/attendance/add', [
            'employee_id' => $me->id,
            'date' => '2026-10-27',
            'check_in' => '09:30',
            'check_out' => '18:30',
            'reason' => 'Giving myself a day.',
        ])->assertSessionHasErrors('employee_id');

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_check_out_must_follow_check_in(): void
    {
        $this->signIn(['employee', 'hr'], 'EMP900');
        $riya = $this->anEmployee('EMP901', 'Riya Sharma');

        $this->post('/attendance/add', [
            'employee_id' => $riya->id,
            'date' => '2026-10-27',
            'check_in' => '18:30',
            'check_out' => '09:30',
            'reason' => 'Times the wrong way round.',
        ])->assertSessionHasErrors('check_out');
    }

    public function test_an_ordinary_employee_cannot_enter_a_day(): void
    {
        $this->signIn(['employee'], 'EMP900');
        $riya = $this->anEmployee('EMP901', 'Riya Sharma');

        $this->get('/attendance/add')->assertForbidden();

        $this->post('/attendance/add', [
            'employee_id' => $riya->id,
            'date' => '2026-10-27',
            'check_in' => '09:30',
            'check_out' => '18:30',
            'reason' => 'Not my place to do this.',
        ])->assertForbidden();

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_hr_sees_the_form_and_the_add_link_on_an_absence(): void
    {
        $this->signIn(['employee', 'hr'], 'EMP900');
        $riya = $this->anEmployee('EMP901', 'Riya Sharma');

        $this->get('/attendance/add?employee='.$riya->id.'&date=2026-10-27')
            ->assertOk()
            ->assertSee('Riya Sharma');

        $this->get('/attendance?date=2026-10-27')
            ->assertOk()
            ->assertSee('Add missed day')
            ->assertSee('/attendance/add?employee='.$riya->id.'&amp;date=2026-10-27', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  list<string>  $roles
     */
    protected function signIn(array $roles, string $staffId = 'EMP800'): Employee
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
}
