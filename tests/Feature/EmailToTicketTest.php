<?php

namespace Tests\Feature;

use App\Mail\SupportAckMail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SupportMailbox;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Realm;
use App\Support\SupportMail\SupportMailIntake;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Email to ticket (owner, 2026-11-06), against a faked Gmail.
 */
class EmailToTicketTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    protected array $inbox = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        SupportMailbox::create([
            'address' => 'support@zephryx.test',
            'account_email' => 'contact@zephryx.test',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'refresh_token' => 'refresh-token',
            'keywords' => SupportMailbox::DEFAULT_KEYWORDS,
            'connected_at' => Carbon::now()->subDay(),
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, 'oauth2.googleapis.com/token') => Http::response(['access_token' => 'access']),
                str_contains($url, '/messages?') => Http::response([
                    'messages' => collect(array_reverse($this->inbox))->map(fn ($m) => ['id' => $m['id'], 'threadId' => $m['threadId']])->values()->all(),
                ]),
                preg_match('#/messages/([^/?]+)#', $url, $m) === 1 => Http::response($this->inbox[$m[1]]),
                default => Http::response([], 404),
            };
        });
    }

    public function test_a_known_clients_email_becomes_their_ticket_with_the_reference_sent_back(): void
    {
        $client = Client::create(['name' => 'Acme School', 'reference' => 'CLT-E1', 'status' => 'active', 'contact_email' => 'office@acme.test']);
        $this->mail('m1', 'office@acme.test', 'Acme Office', 'Cannot log in to the portal', 'The password reset is not working.');

        $this->assertSame(['tickets' => 1, 'replies' => 0, 'ignored' => 0, 'failed' => 0], app(SupportMailIntake::class)->run());

        $ticket = Ticket::firstOrFail();
        $this->assertSame('client', $ticket->type);
        $this->assertSame($client->id, $ticket->client_id);
        $this->assertSame('email', $ticket->source);
        $this->assertSame('Access', $ticket->category);
        $this->assertSame('unassigned', $ticket->status);

        Mail::assertSent(SupportAckMail::class, fn (SupportAckMail $mail) => $mail->hasTo('office@acme.test')
            && $mail->known && $mail->reference === $ticket->reference
            && $mail->fromAddress === 'support@zephryx.test');

        // Run again: the same message never makes a second ticket.
        app(SupportMailIntake::class)->run();
        $this->assertSame(1, Ticket::count());
    }

    public function test_an_unknown_sender_gets_the_automated_reply_and_support_gets_the_ticket(): void
    {
        $this->mail('m2', 'stranger@elsewhere.test', 'A Stranger', 'Quote please', 'How much for a website?');

        app(SupportMailIntake::class)->run();

        $ticket = Ticket::firstOrFail();
        $this->assertSame('internal', $ticket->type);
        $this->assertNull($ticket->raised_by);
        $this->assertSame('stranger@elsewhere.test', $ticket->requester_email);

        Mail::assertSent(SupportAckMail::class, fn (SupportAckMail $mail) => $mail->hasTo('stranger@elsewhere.test')
            && ! $mail->known && $mail->reference === null);

        $rendered = (new SupportAckMail(false, 'support@zephryx.test', 'Quote please', null, null))->render();
        $this->assertStringContainsString('This is an automated reply. We will contact you shortly.', $rendered);
        $this->assertStringContainsString('Kindly contact us using your registered email for quick resolution.', $rendered);
        $this->assertStringContainsString('If in a hurry, call us.', $rendered);
    }

    public function test_an_employees_email_is_their_ticket_and_an_attendance_one_goes_to_hr(): void
    {
        $hr = $this->employee('EMP951', 'hr@zephryx.test', ['employee', 'hr']);
        $employee = $this->employee('EMP952', 'worker@zephryx.test');
        $this->mail('m3', 'worker@zephryx.test', 'Worker', 'Missed check in yesterday', 'My attendance shows absent, I was in from 9:30.');

        app(SupportMailIntake::class)->run();

        $ticket = Ticket::firstOrFail();
        $this->assertSame('internal', $ticket->type);
        $this->assertSame($employee->id, $ticket->raised_by);
        $this->assertSame('Attendance', $ticket->category);
        $this->assertSame($hr->id, $ticket->assignee_id);
    }

    public function test_a_reply_with_the_reference_is_added_to_the_ticket_and_reopens_it(): void
    {
        Client::create(['name' => 'Acme School', 'reference' => 'CLT-E1', 'status' => 'active', 'contact_email' => 'office@acme.test']);
        $this->mail('m4', 'office@acme.test', 'Acme Office', 'Website is down', 'Since morning.');
        app(SupportMailIntake::class)->run();

        $ticket = Ticket::firstOrFail();
        $ticket->update(['status' => 'resolved', 'resolved_at' => Carbon::now()]);

        $this->mail('m5', 'office@acme.test', 'Acme Office', 'Re: Website is down ['.$ticket->reference.']', "Still down.\n\nOn Mon, Support wrote:\n> Fixed it.", 'other-thread');
        // Somebody else quoting the reference does not get into the ticket.
        $this->mail('m6', 'someone@else.test', 'Someone', 'Re: '.$ticket->reference, 'Me too.');

        $counts = app(SupportMailIntake::class)->run();
        $this->assertSame(1, $counts['replies']);
        $this->assertSame(1, $counts['tickets']);

        $comment = TicketComment::where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertSame('Still down.', $comment->body);
        $this->assertFalse($comment->isInternal());
        $this->assertSame('unassigned', $ticket->refresh()->status);
    }

    public function test_auto_replies_and_bounces_are_ignored_and_never_answered(): void
    {
        $this->mail('m7', 'office@acme.test', 'Acme', 'Out of office', 'Away until Monday.', null, [['name' => 'Auto-Submitted', 'value' => 'auto-replied']]);
        $this->mail('m8', 'mailer-daemon@googlemail.com', 'Mail Delivery', 'Delivery failed', 'Could not deliver.');

        $this->assertSame(2, app(SupportMailIntake::class)->run()['ignored']);
        $this->assertSame(0, Ticket::count());
        Mail::assertNothingSent();
        $this->assertSame(2, DB::table('support_mail_messages')->where('outcome', 'ignored')->count());
    }

    public function test_the_admin_sees_the_mailbox_but_never_its_secret(): void
    {
        $this->signInAsAdmin();

        $this->get('/admin/integrations')->assertOk()
            ->assertSee('Support mailbox: email to ticket')
            ->assertSee('support@zephryx.test')
            ->assertDontSee('client-secret')
            ->assertDontSee('refresh-token');
    }

    /* ── helpers ──────────────────────────────────────────────────────────── */

    /**
     * @param  list<array{name: string, value: string}>  $extraHeaders
     */
    protected function mail(string $id, string $from, string $name, string $subject, string $body, ?string $thread = null, array $extraHeaders = []): void
    {
        $this->inbox[$id] = [
            'id' => $id,
            'threadId' => $thread ?? 'thread-'.$id,
            'labelIds' => ['INBOX'],
            'payload' => [
                'mimeType' => 'multipart/alternative',
                'headers' => [
                    ['name' => 'From', 'value' => $name.' <'.$from.'>'],
                    ['name' => 'Subject', 'value' => $subject],
                    ['name' => 'Message-ID', 'value' => '<'.$id.'@mail.test>'],
                    ...$extraHeaders,
                ],
                'parts' => [
                    ['mimeType' => 'text/plain', 'filename' => '', 'body' => ['data' => rtrim(strtr(base64_encode($body), '+/', '-_'), '=')]],
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    protected function employee(string $staffId, string $email, array $roles = ['employee']): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'email' => $email,
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }
}
