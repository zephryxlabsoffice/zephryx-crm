<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\AttendanceDirectory;
use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter;
use App\Support\Audit\AuditLog;
use App\Support\LeaveDirectory;
use App\Support\LeavePresenter as P;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Leave — asking, deciding, and withdrawing.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE RULES THIS FILE HOLDS
 *
 * Nobody decides their own request, a decision is only valid on a pending one,
 * a rejection carries a reason, and the days somebody was granted are the days
 * Attendance stops calling an absence. The last of those is what makes Leave
 * worth having a table for at all: it is the module Attendance asks.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LeaveWritesTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       ASKING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_anybody_with_an_employment_record_may_ask(): void
    {
        // Their own leave is part of the Employee base (§2.2). No permission.
        $employee = $this->signInAsEmployee();

        $this->post('/leave', $this->validPayload())->assertRedirect();

        $request = LeaveRequest::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(P::PENDING, $request->status);
        $this->assertSame('LV-'.now()->year.'-001', $request->reference);
        $this->assertNull($request->decided_by);
    }

    public function test_a_mentor_has_no_leave_to_ask_for(): void
    {
        // No Employee base at all, so no personal records of any kind (§2.1).
        $this->signInAsMentor();

        $this->post('/leave', $this->validPayload())->assertForbidden();
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_a_request_needs_a_reason(): void
    {
        $this->signInAsEmployee();

        $this->post('/leave', $this->validPayload(['reason' => '']))
            ->assertSessionHasErrors('reason');
    }

    public function test_the_last_day_cannot_precede_the_first(): void
    {
        $this->signInAsEmployee();

        $this->post('/leave', $this->validPayload([
            'from' => Carbon::today()->addWeek()->toDateString(),
            'to' => Carbon::today()->addDays(2)->toDateString(),
        ]))->assertSessionHasErrors('to');
    }

    public function test_more_days_than_the_dates_could_contain_is_refused(): void
    {
        /*
         * The only arithmetic in a module that deliberately does no arithmetic.
         * It is not deciding what a range is worth — a week with a holiday in it
         * may well cost four days — it refuses a figure the range cannot contain
         * at all, which is a typo rather than a judgement.
         */
        $this->signInAsEmployee();

        $this->post('/leave', $this->validPayload([
            'from' => Carbon::today()->addDays(3)->toDateString(),
            'to' => Carbon::today()->addDays(4)->toDateString(),
            'days' => 5,
        ]))->assertSessionHasErrors('days');
    }

    public function test_half_days_are_allowed(): void
    {
        // The requester states the cost; half a day is a thing people take.
        $employee = $this->signInAsEmployee();

        $this->post('/leave', $this->validPayload(['days' => 0.5]))->assertRedirect();

        $this->assertSame(0.5, LeaveRequest::where('employee_id', $employee->id)->firstOrFail()->days);
    }

    public function test_the_reason_is_not_written_into_the_audit_log(): void
    {
        /*
         * "Fever, seeing a doctor" is health information. It belongs on the
         * request, in front of the approver, and not in a log the Admin Panel
         * lists by the page.
         */
        $this->signInAsEmployee();

        $this->post('/leave', $this->validPayload(['reason' => 'Fever, seeing a doctor tomorrow']));

        $entry = DB::table('audit_log')->where('action', AuditLog::LEAVE_REQUESTED)->first();

        $this->assertNotNull($entry);
        $this->assertStringNotContainsString('Fever', (string) $entry->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       DECIDING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_deciding_needs_the_permission(): void
    {
        $theirs = $this->aRequestBySomebodyElse();

        $this->signInAsEmployee();

        $this->post('/leave/'.$theirs->reference.'/approve')->assertForbidden();
        $this->post('/leave/'.$theirs->reference.'/reject', ['note' => 'Not this week.'])->assertForbidden();

        $this->assertSame(P::PENDING, $theirs->fresh()->status);
    }

    public function test_hr_may_approve_and_it_is_audited(): void
    {
        $theirs = $this->aRequestBySomebodyElse();

        $this->signInAsEmployee(['employee', 'hr'], 'EMP851');

        $this->post('/leave/'.$theirs->reference.'/approve')->assertRedirect();

        $theirs->refresh();

        $this->assertSame(P::APPROVED, $theirs->status);
        $this->assertNotNull($theirs->decided_by);
        $this->assertNotNull($theirs->decided_at);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::LEAVE_APPROVED)->count());
    }

    public function test_a_rejection_without_a_reason_is_refused(): void
    {
        // The handover's reject button captured nothing, which leaves the person
        // guessing why.
        $theirs = $this->aRequestBySomebodyElse();

        $this->signInAsEmployee(['employee', 'hr'], 'EMP852');

        $this->post('/leave/'.$theirs->reference.'/reject', ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame(P::PENDING, $theirs->fresh()->status);
    }

    public function test_approving_needs_no_reason(): void
    {
        // "Yes" needs no explanation, and demanding one only produces "ok".
        $theirs = $this->aRequestBySomebodyElse();

        $this->signInAsEmployee(['employee', 'hr'], 'EMP853');

        $this->post('/leave/'.$theirs->reference.'/approve')->assertSessionHasNoErrors();
    }

    public function test_nobody_decides_their_own_request_even_holding_the_permission(): void
    {
        /*
         * The one rule in this module that cannot be delegated away. Checked on
         * the write and not only in the view, because a button that is not drawn
         * is not a control — and this is the one somebody would think to try.
         */
        $me = $this->signInAsEmployee(['employee', 'hr']);
        $mine = $this->aRequest($me);

        $this->post('/leave/'.$mine->reference.'/approve')->assertSessionHasErrors('note');

        $this->assertSame(P::PENDING, $mine->fresh()->status);
    }

    public function test_a_second_decision_does_not_overwrite_the_first(): void
    {
        // The status is checked inside the transaction: two approvers opening
        // the same request must not both be able to decide it.
        $theirs = $this->aRequestBySomebodyElse();

        $this->signInAsEmployee(['employee', 'hr'], 'EMP854');

        $this->post('/leave/'.$theirs->reference.'/approve')->assertRedirect();
        $this->post('/leave/'.$theirs->reference.'/reject', ['note' => 'Changed my mind about it.'])
            ->assertRedirect();

        $this->assertSame(P::APPROVED, $theirs->fresh()->status);
        $this->assertSame(0, DB::table('audit_log')->where('action', AuditLog::LEAVE_REJECTED)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       WITHDRAWING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_requester_may_withdraw_their_own_pending_request(): void
    {
        $me = $this->signInAsEmployee();
        $mine = $this->aRequest($me);

        $this->post('/leave/'.$mine->reference.'/cancel', ['note' => 'Plans changed.'])->assertRedirect();

        $this->assertSame(P::CANCELLED, $mine->fresh()->status);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::LEAVE_CANCELLED)->count());
    }

    public function test_nobody_withdraws_somebody_elses_request(): void
    {
        // Refusing somebody's leave is a rejection, with a reason. Calling it a
        // withdrawal would put words in their mouth.
        $theirs = $this->aRequestBySomebodyElse();

        $this->signInAsEmployee(['employee', 'hr'], 'EMP855');

        $this->post('/leave/'.$theirs->reference.'/cancel')->assertForbidden();

        $this->assertSame(P::PENDING, $theirs->fresh()->status);
    }

    public function test_leave_already_under_way_cannot_be_withdrawn(): void
    {
        // A conversation, not a button.
        $me = $this->signInAsEmployee();
        $started = $this->aRequest($me, [
            'status' => P::APPROVED,
            'from_date' => Carbon::today()->subDay(),
            'to_date' => Carbon::today()->addDay(),
        ]);

        $this->post('/leave/'.$started->reference.'/cancel')->assertSessionHasErrors('note');

        $this->assertSame(P::APPROVED, $started->fresh()->status);
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT LEAVE IS FOR: ATTENDANCE ASKS IT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_approved_day_stops_being_an_absence(): void
    {
        /*
         * The reason this module has a table rather than a form. Attendance
         * keeps no copy of who is off — it asks — so the moment a request is
         * approved, the day stops being drawn as an absence.
         */
        $employee = $this->signInAsEmployee(['employee', 'hr']);
        $subject = $this->anEmployee('EMP856', 'Away Person');

        $day = $this->aPastWorkingDay();

        $request = $this->aRequest($subject, [
            'from_date' => $day,
            'to_date' => $day,
        ]);

        // Before the decision: nothing granted, so the day is an absence.
        $before = AttendancePolicy::evaluate(
            $day,
            null,
            in_array($day, AttendanceDirectory::leaveDates($subject->id), true),
        );
        $this->assertSame(AttendancePresenter::ABSENT, $before['state']);

        $this->post('/leave/'.$request->reference.'/approve')->assertRedirect();

        $after = AttendancePolicy::evaluate(
            $day,
            null,
            in_array($day, AttendanceDirectory::leaveDates($subject->id), true),
        );

        $this->assertSame(AttendancePresenter::LEAVE, $after['state']);
        $this->assertNotSame($employee->id, $subject->id);
    }

    public function test_a_withdrawn_request_frees_the_day_again(): void
    {
        // No copy to go stale: the answer changes the moment the record does.
        $me = $this->signInAsEmployee();
        $day = $this->aPastWorkingDay();

        $request = $this->aRequest($me, [
            'status' => P::APPROVED,
            'from_date' => Carbon::today()->addDays(3),
            'to_date' => Carbon::today()->addDays(3),
        ]);

        $granted = Carbon::today()->addDays(3)->toDateString();

        $this->assertContains($granted, AttendanceDirectory::leaveDates($me->id));

        $this->post('/leave/'.$request->reference.'/cancel')->assertRedirect();

        $this->assertNotContains($granted, AttendanceDirectory::leaveDates($me->id));
        // And an unrelated past day was never in the list either way.
        $this->assertNotContains($day, AttendanceDirectory::leaveDates($me->id));
    }

    public function test_a_recorded_attendance_beats_a_granted_day(): void
    {
        /*
         * Somebody who came in on a day they had leave for was here, and the
         * record proves it. Leave only ever explains an ABSENCE — it never
         * erases hours somebody worked.
         */
        $me = $this->signInAsEmployee();
        $day = $this->aPastWorkingDay();

        $this->aRequest($me, ['status' => P::APPROVED, 'from_date' => $day, 'to_date' => $day]);

        AttendanceRecord::create([
            'employee_id' => $me->id,
            'date' => $day,
            'check_in' => '09:10',
            'check_out' => '18:20',
        ]);

        $record = AttendanceDirectory::find(AttendanceRecord::referenceFor($day, 'EMP850'));

        $this->assertNotNull($record);
        $this->assertSame(AttendancePresenter::PRESENT, $record['state']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'casual',
            'from' => Carbon::today()->addWeek()->toDateString(),
            'to' => Carbon::today()->addWeek()->toDateString(),
            'days' => 1,
            'reason' => 'Personal work',
            'contact' => '+91 98200 11223',
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    protected function signInAsEmployee(array $roles = ['employee'], string $staffId = 'EMP850'): Employee
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
    protected function aRequest(Employee $employee, array $attributes = []): LeaveRequest
    {
        return LeaveRequest::create($attributes + [
            'reference' => 'LV-TEST-'.LeaveRequest::count().'-'.$employee->id,
            'employee_id' => $employee->id,
            'type' => 'casual',
            'from_date' => Carbon::today()->addWeek(),
            'to_date' => Carbon::today()->addWeek(),
            'days' => 1,
            'reason' => 'Personal work',
            'status' => P::PENDING,
            'applied_at' => Carbon::now(),
        ]);
    }

    protected function aRequestBySomebodyElse(): LeaveRequest
    {
        return $this->aRequest($this->anEmployee('EMP849', 'Their Person'));
    }

    /**
     * A past working day, found rather than assumed — a fixed offset lands on a
     * weekend often enough to fail on the calendar rather than on the code.
     */
    protected function aPastWorkingDay(): string
    {
        $day = Carbon::today()->subDay();

        while (! AttendancePolicy::isWorkingDay($day)) {
            $day->subDay();
        }

        return $day->toDateString();
    }
}
