<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\AnnouncementPresenter;
use App\Support\AttendancePolicy;
use App\Support\Audit\AuditLog;
use App\Support\Holidays;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Announcements — posting to the board, and closing the office by doing it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE HALF OF THIS FILE THAT IS REALLY ABOUT ATTENDANCE
 *
 * A holiday notice carries the days the office is shut, and App\Support\Holidays
 * reads them so nobody is marked absent on those days. That makes this form an
 * attendance write, and the tests below hold what follows: the dates are their
 * own permission, a draft closes nothing, and taking a notice down does not
 * reopen a day that was already closed.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AnnouncementWritesTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       POSTING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_posting_is_its_own_permission(): void
    {
        // A board nobody can post to is a board nobody reads, so posting is
        // broad — but it is not everybody.
        $this->signInAsEmployee();

        $this->get('/announcements')->assertOk();
        $this->get('/announcements/compose')->assertForbidden();
        $this->post('/announcements', $this->validPayload())->assertForbidden();
    }

    public function test_hr_may_post_and_it_goes_on_the_board(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['publish' => 1]))->assertRedirect();

        $announcement = Announcement::firstOrFail();

        $this->assertNotNull($announcement->published_at);
        $this->assertSame('ANN-'.now()->year.'-001', $announcement->reference);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ANNOUNCEMENT_POSTED)->count());
    }

    public function test_a_draft_is_saved_without_going_up(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['publish' => 0]))->assertRedirect();

        $announcement = Announcement::firstOrFail();

        $this->assertNull($announcement->published_at);
        $this->assertSame(AnnouncementPresenter::DRAFT, $announcement->toRecordArray()['status']);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ANNOUNCEMENT_DRAFTED)->count());
    }

    public function test_a_narrowed_audience_must_name_a_department(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload([
            'audience' => 'department',
            'audience_department_id' => null,
        ]))->assertSessionHasErrors('audience_department_id');
    }

    /* ══════════════════════════════════════════════════════════════════════
       CLOSING THE OFFICE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_published_holiday_notice_closes_the_office(): void
    {
        /*
         * One list, and this is it. HR posts the notice people read and the
         * same row decides who was not absent — the alternative is a holiday
         * calendar in the Admin Panel that goes stale against the board.
         */
        $this->signInAsHr();

        $closure = Carbon::today()->addDays(6)->toDateString();

        $this->post('/announcements', $this->validPayload([
            'category' => 'holiday',
            'observed_from' => $closure,
            'observed_to' => $closure,
            'publish' => 1,
        ]))->assertRedirect();

        $this->assertFalse(AttendancePolicy::isWorkingDay($closure));
        $this->assertSame('Office closed on Friday', Holidays::on($closure));
    }

    public function test_a_drafted_holiday_closes_nothing(): void
    {
        /*
         * Somebody writing "office closed for Diwali" while the dates are still
         * being confirmed must not have already changed everybody's attendance
         * record.
         */
        $this->signInAsHr();

        $closure = Carbon::today()->addDays(7)->toDateString();

        $this->post('/announcements', $this->validPayload([
            'category' => 'holiday',
            'observed_from' => $closure,
            'observed_to' => $closure,
            'publish' => 0,
        ]))->assertRedirect();

        $this->assertNull(Holidays::on($closure));
        $this->assertTrue(AttendancePolicy::isWorkingDay($closure) || AttendancePolicy::isWeekOff($closure));
    }

    public function test_somebody_who_may_post_but_not_close_the_office_still_gets_their_notice(): void
    {
        /*
         * The dates are dropped, not the post. Refusing the whole thing would
         * lose what somebody wrote over a field they did not know they could
         * not use — and what must not happen is the attendance record changing
         * underneath the notice.
         */
        $this->signInAsPoster();

        $closure = Carbon::today()->addDays(8)->toDateString();

        $this->post('/announcements', $this->validPayload([
            'category' => 'holiday',
            'observed_from' => $closure,
            'observed_to' => $closure,
            'publish' => 1,
        ]))->assertRedirect();

        $announcement = Announcement::firstOrFail();

        $this->assertNotNull($announcement->published_at, 'the notice was refused rather than trimmed');
        $this->assertNull($announcement->observed_from);
        $this->assertNull(Holidays::on($closure));
    }

    public function test_a_draft_that_closes_the_office_cannot_be_published_by_somebody_without_the_permission(): void
    {
        // Otherwise the permission is a formality anybody routes around by
        // asking a colleague to press the button.
        $hr = $this->signInAsHr();

        $closure = Carbon::today()->addDays(9)->toDateString();

        $this->post('/announcements', $this->validPayload([
            'category' => 'holiday',
            'observed_from' => $closure,
            'observed_to' => $closure,
            'publish' => 0,
        ]));

        $announcement = Announcement::firstOrFail();

        $this->signInAsPoster();

        $this->post('/announcements/'.$announcement->reference.'/publish')->assertForbidden();

        $this->assertNull($announcement->fresh()->published_at);
        $this->assertNull(Holidays::on($closure));
        $this->assertNotNull($hr);
    }

    public function test_a_closure_longer_than_a_fortnight_is_refused(): void
    {
        // Not a holiday notice — a typo somebody would otherwise find in next
        // month's attendance report.
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload([
            'category' => 'holiday',
            'observed_from' => Carbon::today()->addDay()->toDateString(),
            'observed_to' => Carbon::today()->addDays(40)->toDateString(),
            'publish' => 1,
        ]))->assertSessionHasErrors('observed_to');
    }

    public function test_taking_a_holiday_notice_down_does_not_reopen_the_day(): void
    {
        /*
         * The office WAS shut on those days whatever the board says about it
         * now. Clearing them would retroactively mark everybody absent for a
         * day the company closed.
         */
        $this->signInAsHr();

        $closure = Carbon::today()->subDays(3)->toDateString();

        $this->post('/announcements', $this->validPayload([
            'category' => 'holiday',
            'starts_on' => Carbon::today()->subDays(5)->toDateString(),
            'observed_from' => $closure,
            'observed_to' => $closure,
            'publish' => 1,
        ]));

        $announcement = Announcement::firstOrFail();

        $this->post('/announcements/'.$announcement->reference.'/expire')->assertRedirect();

        $this->assertNotNull($announcement->fresh()->observed_from);
        $this->assertSame('Office closed on Friday', Holidays::on($closure));
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ANNOUNCEMENT_EXPIRED)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       CATEGORIES, FROM MASTER DATA
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_unknown_category_is_refused(): void
    {
        // Categories are master data now (decided 2026-09-21) — a category
        // nobody set up in the Admin Panel cannot be posted against.
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['category' => 'not-a-real-category']))
            ->assertSessionHasErrors('category');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CLIENT BOARD
       ══════════════════════════════════════════════════════════════════════ */

    public function test_for_clients_is_off_by_default_and_can_be_set(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload())->assertRedirect();
        $this->assertFalse(Announcement::firstOrFail()->for_clients);

        $this->post('/announcements', $this->validPayload(['for_clients' => 1]))->assertRedirect();
        $this->assertTrue(Announcement::latest('id')->firstOrFail()->for_clients);
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT IS NEVER A ROW
       ══════════════════════════════════════════════════════════════════════ */

    public function test_no_route_creates_a_milestone(): void
    {
        /*
         * Birthdays and anniversaries are computed from employee records on
         * every request. A stored one would be wrong the following year and
         * would survive somebody opting out of having theirs announced.
         */
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['category' => 'milestone']))
            ->assertSessionHasErrors('category');

        $this->assertSame(0, Announcement::where('category', 'milestone')->count());
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
            'title' => 'Office closed on Friday',
            'body' => 'The office is closed. Client calls have been moved.',
            'category' => 'policy',
            'audience' => 'everyone',
            'starts_on' => Carbon::today()->toDateString(),
            'publish' => 1,
        ];
    }

    protected function signInAsEmployee(): Employee
    {
        return $this->signInWith(['employee'], 'EMP940');
    }

    /** HR: may post, and may close the office. */
    protected function signInAsHr(): Employee
    {
        return $this->signInWith(['employee', 'hr'], 'EMP941');
    }

    /** A project manager: may post, may not close the office. */
    protected function signInAsPoster(): Employee
    {
        return $this->signInWith(['employee', 'manager'], 'EMP942');
    }

    /**
     * @param  list<string>  $roles
     */
    protected function signInWith(array $roles, string $staffId): Employee
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
}
