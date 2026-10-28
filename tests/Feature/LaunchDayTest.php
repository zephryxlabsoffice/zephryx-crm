<?php

namespace Tests\Feature;

use App\Mail\LaunchWelcomeMail;
use App\Support\Launch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Launch day (owner, 2026-10-28): "Attendance starts on" can be set ahead,
 * and on the day everybody is emailed a welcome and sees one on their first
 * visit.
 */
class LaunchDayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow('2026-10-28 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_launch_date_can_be_set_in_the_future(): void
    {
        $this->signInAsAdmin();

        $this->get('/admin/settings')->assertOk()->assertDontSee('max="2026-10-28"', false);

        $this->post('/admin/settings', ['key' => 'attendance.starts_on', 'value' => '2026-11-01'])
            ->assertRedirect('/admin/settings');

        $this->assertSame('2026-11-01', config('attendance.starts_on'));
    }

    public function test_nothing_before_launch_day(): void
    {
        config(['attendance.starts_on' => '2026-11-01']);
        $this->signInAsStaff(['employee']);

        $this->get('/dashboard')->assertOk()->assertDontSee('We are live!');
        Mail::assertNothingSent();
    }

    public function test_on_launch_day_everybody_is_emailed_once_and_welcomed_once(): void
    {
        config(['attendance.starts_on' => '2026-11-01']);
        $staff = $this->signInAsStaff(['employee']);
        Carbon::setTestNow('2026-11-01 09:00:00');

        $this->get('/dashboard')->assertOk()->assertSee('We are live!');
        $this->get('/dashboard')->assertOk()->assertDontSee('We are live!');

        Mail::assertSent(LaunchWelcomeMail::class, 1);
        Mail::assertSent(LaunchWelcomeMail::class, fn (LaunchWelcomeMail $m) => $m->hasBcc($staff->email));

        // The nightly job does not send it again.
        $this->assertSame(0, Launch::sendWelcomeIfDue());
        Mail::assertSent(LaunchWelcomeMail::class, 1);
    }

    public function test_the_nightly_job_sends_it_at_two_in_the_morning(): void
    {
        config(['attendance.starts_on' => '2026-11-01']);
        $this->signInAsStaff(['employee']);
        Carbon::setTestNow('2026-11-01 02:00:00');

        $this->assertSame(1, Launch::sendWelcomeIfDue());
        Mail::assertSent(LaunchWelcomeMail::class, 1);
    }

    public function test_somebody_first_in_after_launch_day_still_gets_the_popup_but_no_late_email(): void
    {
        config(['attendance.starts_on' => '2026-11-01']);
        $this->signInAsStaff(['employee']);
        Carbon::setTestNow('2026-11-03 09:00:00');

        $this->get('/dashboard')->assertOk()->assertSee('We are live!');
        Mail::assertNothingSent();
    }

    public function test_moving_the_launch_later_welcomes_everybody_again_on_the_new_day(): void
    {
        config(['attendance.starts_on' => '2026-11-01']);
        $staff = $this->signInAsStaff(['employee']);
        Carbon::setTestNow('2026-11-01 09:00:00');
        $this->get('/dashboard')->assertSee('We are live!');

        config(['attendance.starts_on' => '2026-11-10']);
        Carbon::setTestNow('2026-11-10 09:00:00');
        // Nine days on, the old session has expired; a fresh one, as a new sign-in.
        $this->flushSession();
        $this->actingAs($staff->fresh())->get('/dashboard')->assertSee('We are live!');
    }

    public function test_the_email_renders(): void
    {
        $html = (new LaunchWelcomeMail('2026-11-01'))->render();

        $this->assertStringContainsString('Sunday 1 November 2026', $html);
        $this->assertStringContainsString('counts as absent', $html);
    }
}
