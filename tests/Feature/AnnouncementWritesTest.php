<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\MasterDataItem;
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
 * Announcements - posting to the board, and closing the office by doing it.
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
        // broad - but it is not everybody.
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

    public function test_a_draft_can_be_published_and_then_taken_down_from_its_page(): void
    {
        // The buttons on the announcement page shipped disabled long after the
        // writes behind them worked, so a draft could never go up later.
        $this->signInAsHr();
        $this->post('/announcements', $this->validPayload(['publish' => 0]));

        $announcement = Announcement::firstOrFail();
        $page = '/announcements/'.$announcement->reference;

        $html = $this->get($page)->assertOk()->getContent();
        $this->assertStringContainsString('Publish now', $html);
        $this->assertStringNotContainsString('not built yet', $html);

        $this->post($page.'/publish')->assertRedirect();
        $this->assertNotNull($announcement->fresh()->published_at);

        $html = $this->get($page)->assertOk()->getContent();
        $this->assertStringContainsString('Take off the board', $html);
        $this->assertStringNotContainsString('not built yet', $html);
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
         * same row decides who was not absent - the alternative is a holiday
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
         * not use - and what must not happen is the attendance record changing
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
        // Not a holiday notice - a typo somebody would otherwise find in next
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
        // Categories are master data now (decided 2026-09-21) - a category
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
       THE FORM FOLLOWS THE CATEGORY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_holiday_needs_only_its_name_and_dates(): void
    {
        $this->signInAsHr();

        $closure = Carbon::today()->addDays(6);

        $this->post('/announcements', [
            'title' => 'Diwali',
            'category' => 'holiday',
            'closes_office' => 1,
            'observed_from' => $closure->toDateString(),
            'audience' => 'everyone',
            'starts_on' => Carbon::today()->toDateString(),
            'publish' => 1,
        ])->assertSessionHasNoErrors();

        $announcement = Announcement::firstOrFail();

        $this->assertSame('The office is closed on '.$closure->format('l j F Y').'.', $announcement->body);
        $this->assertSame($closure->toDateString(), $announcement->observed_to->toDateString());
        $this->assertSame($closure->toDateString(), $announcement->ends_on->toDateString());
        $this->assertSame('Diwali', Holidays::on($closure));
    }

    public function test_saying_the_office_closes_needs_a_date(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['category' => 'holiday', 'closes_office' => 1]))
            ->assertSessionHasErrors('observed_from');
    }

    public function test_a_holiday_reminder_closes_nothing_and_needs_a_message(): void
    {
        $this->signInAsHr();

        $closure = Carbon::today()->addDays(6)->toDateString();
        $reminder = [
            'category' => 'holiday',
            'closes_office' => 0,
            'observed_from' => $closure,
        ];

        $this->post('/announcements', $this->validPayload($reminder + ['body' => '']))
            ->assertSessionHasErrors('body');

        $this->post('/announcements', $this->validPayload($reminder))->assertSessionHasNoErrors();

        $this->assertNull(Announcement::firstOrFail()->observed_from);
        $this->assertNull(Holidays::on($closure));
    }

    public function test_closure_dates_on_another_category_are_not_kept(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload([
            'category' => 'event',
            'event_date' => Carbon::today()->addDay()->toDateString(),
            'observed_from' => Carbon::today()->addDay()->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Announcement::firstOrFail()->observed_from);
    }

    public function test_an_event_needs_a_date_and_its_details_reach_the_text(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['category' => 'event']))
            ->assertSessionHasErrors('event_date');

        $eventDay = Carbon::today()->addDays(10);

        $this->post('/announcements', [
            'title' => 'Annual day',
            'category' => 'event',
            'event_date' => $eventDay->toDateString(),
            'event_time' => '15:30',
            'event_place' => 'Main hall',
            'audience' => 'everyone',
            'starts_on' => Carbon::today()->toDateString(),
            'publish' => 1,
        ])->assertSessionHasNoErrors();

        $announcement = Announcement::firstOrFail();

        $this->assertSame(
            'Event date: '.$eventDay->format('l j F Y')."\nTime: 3:30 PM\nVenue: Main hall",
            $announcement->body,
        );
        $this->assertSame($eventDay->toDateString(), $announcement->ends_on->toDateString());
    }

    public function test_a_notice_that_needs_a_message_is_refused_without_one(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['category' => 'policy', 'body' => '']))
            ->assertSessionHasErrors('body');
    }

    public function test_a_late_notice_about_a_past_closure_is_not_born_expired(): void
    {
        // A late notice must not be born expired: it comes off no earlier than it goes up.
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload([
            'category' => 'holiday',
            'observed_from' => Carbon::today()->subDays(2)->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(Carbon::today()->toDateString(), Announcement::firstOrFail()->ends_on->toDateString());
    }

    public function test_only_somebody_who_may_close_the_office_is_asked_for_the_dates(): void
    {
        $this->signInAsPoster();
        $this->get('/announcements/compose')->assertOk()->assertDontSee('name="observed_from"', false);

        $this->signInAsHr();
        $this->get('/announcements/compose')->assertOk()->assertSee('name="observed_from"', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ON THE BOARD, AND WHO READS IT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_future_start_date_is_scheduled_until_the_day_comes(): void
    {
        $this->signInAsHr();

        $startsOn = Carbon::today()->addDays(3);

        $this->post('/announcements', $this->validPayload(['starts_on' => $startsOn->toDateString()]))
            ->assertRedirect();

        $announcement = Announcement::firstOrFail();

        $this->assertSame(AnnouncementPresenter::SCHEDULED, $announcement->toRecordArray()['status']);
        $this->get('/announcements')->assertDontSee($announcement->title);

        Carbon::setTestNow($startsOn->copy()->setTime(0, 30));

        $this->assertSame(AnnouncementPresenter::ACTIVE, $announcement->fresh()->toRecordArray()['status']);
        $this->get('/announcements')->assertSee($announcement->title);

        Carbon::setTestNow();
    }

    public function test_managers_only_does_not_ask_for_a_department(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['audience' => 'managers']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Announcement::firstOrFail()->audience_department_id);
    }

    public function test_a_narrowed_post_reaches_only_those_it_is_for(): void
    {
        $this->signInAsHr();
        $sales = MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->active()->firstOrFail();

        $this->post('/announcements', $this->validPayload(['title' => 'For managers', 'audience' => 'managers']));
        $this->post('/announcements', $this->validPayload([
            'title' => 'For one department',
            'audience' => 'department',
            'audience_department_id' => $sales->id,
        ]));

        $forManagers = Announcement::where('title', 'For managers')->firstOrFail();
        $forDepartment = Announcement::where('title', 'For one department')->firstOrFail();

        $outsider = $this->signInAsEmployee();
        $this->get('/announcements')->assertDontSee('For managers')->assertDontSee('For one department');
        $this->get('/announcements/'.$forManagers->reference)->assertNotFound();
        $this->get('/announcements/'.$forDepartment->reference)->assertNotFound();

        $outsider->update(['department_id' => $sales->id]);
        $this->get('/announcements')->assertDontSee('For managers')->assertSee('For one department');
        $this->get('/announcements/'.$forDepartment->reference)->assertOk();

        $this->signInWith(['employee', 'manager'], 'EMP943');
        $this->get('/announcements')->assertSee('For managers');
    }

    public function test_a_draft_or_scheduled_post_cannot_be_opened_by_a_reader(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload(['title' => 'Draft', 'publish' => 0]));
        $this->post('/announcements', $this->validPayload([
            'title' => 'Later',
            'starts_on' => Carbon::today()->addDays(2)->toDateString(),
        ]));

        $this->signInAsEmployee();

        foreach (Announcement::all() as $announcement) {
            $this->get('/announcements/'.$announcement->reference)->assertNotFound();
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       EDITING, CANCELLING, DELETING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_correcting_a_closure_date_moves_the_closure_and_rewrites_the_text(): void
    {
        $this->signInAsHr();

        $typed = Carbon::today()->addDays(4);
        $meant = Carbon::today()->addDays(14);
        $announcement = $this->postedHoliday($typed);

        $this->post('/announcements/'.$announcement->reference.'/update', [
            'title' => 'Diwali',
            'category' => 'holiday',
            'closes_office' => 1,
            'observed_from' => $meant->toDateString(),
            'audience' => 'everyone',
            'starts_on' => Carbon::today()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $announcement->refresh();

        $this->assertNull(Holidays::on($typed));
        $this->assertSame('Diwali', Holidays::on($meant));
        $this->assertSame('The office is closed on '.$meant->format('l j F Y').'.', $announcement->body);
        $this->assertSame($meant->toDateString(), $announcement->ends_on->toDateString());
        $this->assertNotNull($announcement->published_at);

        $entry = DB::table('audit_log')->where('action', AuditLog::ANNOUNCEMENT_EDITED)->sole();

        $this->assertStringContainsString($typed->format('d M Y'), json_encode($entry));
        $this->assertStringContainsString($meant->format('d M Y'), json_encode($entry));
    }

    public function test_the_edit_form_opens_with_the_answers_not_the_written_text(): void
    {
        $this->signInAsHr();

        $this->post('/announcements', $this->validPayload([
            'title' => 'Annual day',
            'category' => 'event',
            'body' => 'Bring a friend.',
            'event_date' => Carbon::today()->addDays(10)->toDateString(),
            'event_place' => 'Main hall',
        ]));

        $this->get('/announcements/'.Announcement::firstOrFail()->reference.'/edit')
            ->assertOk()
            ->assertSee('>Bring a friend.</textarea>', false)
            ->assertSee('value="Main hall"', false)
            ->assertSee('value="event" required checked', false);
    }

    public function test_a_post_from_before_details_were_kept_can_still_be_corrected(): void
    {
        $this->signInAsHr();

        $closure = Carbon::today()->addDays(4);
        $announcement = $this->postedHoliday($closure);
        $sentence = 'The office is closed on '.$closure->format('l j F Y').'.';
        $announcement->update(['details' => null, 'body' => $sentence."\n\nCalls moved."]);

        $this->get('/announcements/'.$announcement->reference.'/edit')
            ->assertSee('>Calls moved.</textarea>', false);
    }

    public function test_a_draft_can_go_up_from_the_edit_form_and_a_posted_one_stays_up(): void
    {
        $this->signInAsHr();
        $this->post('/announcements', $this->validPayload(['publish' => 0]));

        $announcement = Announcement::firstOrFail();
        $page = '/announcements/'.$announcement->reference.'/update';

        $this->post($page, $this->validPayload(['publish' => 0]))->assertRedirect();
        $this->assertNull($announcement->fresh()->published_at);

        $this->post($page, $this->validPayload(['publish' => 1]))->assertRedirect();
        $this->assertNotNull($announcement->fresh()->published_at);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ANNOUNCEMENT_POSTED)->count());

        $this->post($page, $this->validPayload(['publish' => 0]))->assertRedirect();
        $this->assertNotNull($announcement->fresh()->published_at);
    }

    public function test_correcting_an_expired_post_does_not_put_it_back_on_the_board(): void
    {
        $this->signInAsHr();
        $this->post('/announcements', $this->validPayload());

        $announcement = Announcement::firstOrFail();
        $this->post('/announcements/'.$announcement->reference.'/expire');

        $this->post('/announcements/'.$announcement->reference.'/update', [
            'title' => 'Corrected',
            'body' => 'Corrected text.',
            'category' => 'policy',
            'audience' => 'everyone',
        ])->assertSessionHasNoErrors();

        $fresh = $announcement->fresh();

        $this->assertSame('Corrected', $fresh->title);
        $this->assertSame(AnnouncementPresenter::EXPIRED, $fresh->toRecordArray()['status']);
    }

    public function test_an_edit_is_held_to_the_same_rules_as_a_new_post(): void
    {
        $this->signInAsHr();
        $this->post('/announcements', $this->validPayload());

        $this->post('/announcements/'.Announcement::firstOrFail()->reference.'/update', $this->validPayload(['body' => '']))
            ->assertSessionHasErrors('body');
    }

    public function test_only_the_holiday_permission_may_change_a_post_that_closes_the_office(): void
    {
        $this->signInAsHr();

        $closure = Carbon::today()->addDays(5);
        $announcement = $this->postedHoliday($closure);
        $page = '/announcements/'.$announcement->reference;

        $this->signInAsPoster();

        $this->get($page.'/edit')->assertForbidden();
        $this->post($page.'/update', $this->validPayload())->assertForbidden();
        $this->post($page.'/delete')->assertForbidden();
        $this->get($page)->assertOk()->assertDontSee($page.'/edit', false)->assertDontSee($page.'/delete', false);

        $this->assertSame('Diwali', Holidays::on($closure));
    }

    public function test_somebody_who_may_only_read_cannot_change_anything(): void
    {
        $this->signInAsHr();
        $this->post('/announcements', $this->validPayload());

        $page = '/announcements/'.Announcement::firstOrFail()->reference;

        $this->signInAsEmployee();

        $this->get($page.'/edit')->assertForbidden();
        $this->post($page.'/update', $this->validPayload())->assertForbidden();
        $this->post($page.'/cancel')->assertForbidden();
        $this->post($page.'/delete')->assertForbidden();
        $this->assertSame(1, Announcement::count());
    }

    public function test_cancelling_a_scheduled_post_makes_it_a_draft_that_closes_nothing(): void
    {
        $this->signInAsHr();

        $closure = Carbon::today()->addDays(9);

        $this->post('/announcements', $this->validPayload([
            'category' => 'holiday',
            'observed_from' => $closure->toDateString(),
            'starts_on' => Carbon::today()->addDays(3)->toDateString(),
        ]));

        $announcement = Announcement::firstOrFail();
        $this->assertNotNull(Holidays::on($closure));

        $this->post('/announcements/'.$announcement->reference.'/cancel')->assertRedirect();

        $this->assertSame(AnnouncementPresenter::DRAFT, $announcement->fresh()->toRecordArray()['status']);
        $this->assertNull(Holidays::on($closure));
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ANNOUNCEMENT_CANCELLED)->count());
    }

    public function test_only_a_scheduled_post_can_be_cancelled(): void
    {
        $this->signInAsHr();
        $this->post('/announcements', $this->validPayload());

        $announcement = Announcement::firstOrFail();

        $this->post('/announcements/'.$announcement->reference.'/cancel')->assertRedirect();

        $this->assertNotNull($announcement->fresh()->published_at);
    }

    public function test_deleting_removes_the_post_and_the_audit_log_keeps_its_text(): void
    {
        $this->signInAsHr();
        $this->post('/announcements', $this->validPayload(['body' => 'Words worth keeping.']));

        $reference = Announcement::firstOrFail()->reference;

        $this->post('/announcements/'.$reference.'/delete')->assertRedirect(route('announcements.manage'));

        $this->assertSame(0, Announcement::count());
        $this->get('/announcements/'.$reference)->assertNotFound();
        $this->assertStringContainsString(
            'Words worth keeping.',
            json_encode(DB::table('audit_log')->where('action', AuditLog::ANNOUNCEMENT_DELETED)->sole()),
        );
    }

    public function test_deleting_a_closure_reopens_its_days_and_warns_first(): void
    {
        $this->signInAsHr();

        $closure = Carbon::today()->addDays(5);
        $announcement = $this->postedHoliday($closure);
        $page = '/announcements/'.$announcement->reference;

        $this->get($page)->assertSee('The office is no longer closed on');

        $this->post($page.'/delete')->assertRedirect();

        $this->assertNull(Holidays::on($closure));
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

    protected function postedHoliday(Carbon $closure): Announcement
    {
        $this->post('/announcements', [
            'title' => 'Diwali',
            'category' => 'holiday',
            'closes_office' => 1,
            'observed_from' => $closure->toDateString(),
            'audience' => 'everyone',
            'starts_on' => Carbon::today()->toDateString(),
            'publish' => 1,
        ])->assertSessionHasNoErrors();

        return Announcement::latest('id')->firstOrFail();
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
