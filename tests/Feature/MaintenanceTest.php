<?php

namespace Tests\Feature;

use App\Mail\MaintenanceMail;
use App\Models\User;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Admin → Maintenance (owner, 2026-10-28): nobody but the Admin Panel uses the
 * site while it is on, and everybody is told when it starts and ends.
 */
class MaintenanceTest extends TestCase
{
    protected User $admin;

    protected User $staff;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->staff = $this->signInAsStaff(['employee']);
        $this->client = $this->signInAsClient();
        $this->admin = $this->signInAsAdmin();
    }

    public function test_now_closes_the_site_to_everybody_but_the_admin_and_emails_them(): void
    {
        $this->post('/admin/maintenance/now', ['note' => 'Uploading the new version'])
            ->assertRedirect('/admin/maintenance');

        Mail::assertSent(MaintenanceMail::class, fn (MaintenanceMail $m) => $m->kind === MaintenanceMail::STARTED
            && $m->hasBcc($this->staff->email) && $m->hasBcc($this->client->email)
            && ! $m->hasBcc($this->admin->email));

        $this->actingAs($this->staff)->get('/dashboard')->assertStatus(503)->assertSee('We are doing some maintenance');
        $this->actingAs($this->client)->get('/client/dashboard')->assertStatus(503);
        auth()->logout();
        $this->get('/login')->assertOk();
        $this->actingAs($this->staff)->get('/')->assertStatus(503);

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->assertSee('Maintenance is on');
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::MAINTENANCE_STARTED)->count());
    }

    public function test_ending_opens_the_site_and_says_it_is_back(): void
    {
        $this->post('/admin/maintenance/now');
        $this->post('/admin/maintenance/end')->assertRedirect('/admin/maintenance');

        Mail::assertSent(MaintenanceMail::class, fn (MaintenanceMail $m) => $m->kind === MaintenanceMail::ENDED);

        $this->actingAs($this->staff)->get('/dashboard')->assertOk();
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::MAINTENANCE_ENDED)->count());
    }

    public function test_a_schedule_warns_first_closes_in_the_window_and_reopens_by_itself(): void
    {
        $from = Carbon::now()->addDay()->setTime(22, 0);
        $to = $from->copy()->addHours(2);

        $this->post('/admin/maintenance/schedule', [
            'from' => $from->format('Y-m-d\TH:i'),
            'to' => $to->format('Y-m-d\TH:i'),
        ])->assertRedirect('/admin/maintenance');

        Mail::assertSent(MaintenanceMail::class, fn (MaintenanceMail $m) => $m->kind === MaintenanceMail::SCHEDULED
            && $m->startsAt === $from->toDateTimeString() && $m->endsAt === $to->toDateTimeString());

        // Before: open, with the warning on every page.
        $this->actingAs($this->staff)->get('/dashboard')->assertOk()->assertSee('Planned maintenance');

        // During: closed.
        Carbon::setTestNow($from->copy()->addMinutes(5));
        $this->actingAs($this->staff)->get('/dashboard')->assertStatus(503);

        // After: open again with nobody pressing anything.
        Carbon::setTestNow($to->copy()->addMinute());
        $this->actingAs($this->staff)->get('/dashboard')->assertOk()->assertDontSee('Planned maintenance');

        Carbon::setTestNow();
    }

    public function test_calling_off_a_schedule_says_so(): void
    {
        $this->post('/admin/maintenance/schedule', [
            'from' => now()->addDay()->format('Y-m-d\TH:i'),
            'to' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
        ]);

        $this->post('/admin/maintenance/end');

        Mail::assertSent(MaintenanceMail::class, fn (MaintenanceMail $m) => $m->kind === MaintenanceMail::CANCELLED);
        $this->actingAs($this->staff)->get('/dashboard')->assertOk()->assertDontSee('Planned maintenance');
    }

    public function test_a_schedule_must_be_in_the_future_and_end_after_it_starts(): void
    {
        $this->post('/admin/maintenance/schedule', [
            'from' => now()->subHour()->format('Y-m-d\TH:i'),
            'to' => now()->subHours(2)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors(['from', 'to']);
    }

    public function test_a_mail_failure_still_switches_and_says_the_emails_did_not_go(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP refused'));

        $this->post('/admin/maintenance/now')
            ->assertSessionHas('status_tone', 'warning');

        $this->actingAs($this->staff)->get('/dashboard')->assertStatus(503);
    }

    public function test_only_the_admin_panel_can_switch_it(): void
    {
        // The Admin Panel answers staff with a 404, as it does everywhere.
        $this->actingAs($this->staff)->post('/admin/maintenance/now')->assertNotFound();
        $this->actingAs($this->staff)->get('/dashboard')->assertOk();
    }

    public function test_every_email_renders_in_html_and_text(): void
    {
        foreach ([MaintenanceMail::STARTED, MaintenanceMail::SCHEDULED, MaintenanceMail::ENDED, MaintenanceMail::CANCELLED] as $kind) {
            $mail = new MaintenanceMail($kind, '2026-11-01 22:00:00', '2026-11-02 00:00:00', 'Moving servers');

            $html = $mail->render();
            $this->assertStringContainsString('Moving servers', $html);

            if ($kind === MaintenanceMail::SCHEDULED) {
                $this->assertStringContainsString('Sun 1 Nov 2026, 10:00 PM', $html);
                $this->assertStringContainsString('Mon 2 Nov 2026, 12:00 AM', $html);
            }
        }
    }
}
