<?php

namespace Tests\Feature;

use App\Models\EmployeeBanking;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seeing one full identifier, deliberately.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A REVEAL IS AN ACT, NOT A VIEW (decided 2026-09-14)
 *
 * The masked card is what a page shows. This is somebody choosing to look at a
 * particular number, for a stated reason, and it leaves a permanent record of
 * having done so. Three properties follow, and each one is a test below:
 *
 *   IT IS A POST. A GET would be a link — prefetched by a browser, sitting in
 *   history, retried on a back button — and every one of those would write an
 *   audit entry for a look nobody took, which makes the log useless in exactly
 *   the situation it exists for.
 *
 *   IT IS ONE FIELD. "Show me this person's details" is the page. This answers
 *   "show me their account number, because a transfer bounced".
 *
 *   IT IS NOT REMEMBERED. The value is flashed for a single render. A reveal
 *   that persisted would be a page that quietly shows full identifiers to
 *   whoever opens it next.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmployeeIdentityRevealTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoWorkforce();
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO MAY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_directory_permission_does_not_allow_a_reveal(): void
    {
        // A Team Lead may look somebody up. That is not this.
        $this->signInAsStaff(['employee', 'team_lead']);

        $this->post('/employees/'.$this->subject().'/reveal', [
            'field' => 'account_number',
            'reason' => 'A transfer bounced.',
        ])->assertForbidden();
    }

    public function test_hr_may_reveal_one_field(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $banking = $this->banking();

        $this->post('/employees/'.$this->subject().'/reveal', [
            'field' => 'account_number',
            'reason' => 'A transfer bounced and the bank asked us to confirm it.',
        ])->assertRedirect();

        $html = $this->get('/employees/'.$this->subject())->getContent();

        $this->assertStringContainsString($banking->account_number, $html);
    }

    public function test_only_the_field_asked_for_comes_back(): void
    {
        // The others stay masked on the same page. Revealing one is not
        // revealing the record.
        $this->signInAsStaff(['employee', 'hr']);

        $banking = $this->banking();

        $this->post('/employees/'.$this->subject().'/reveal', [
            'field' => 'account_number',
            'reason' => 'Confirming a bounced transfer.',
        ]);

        $html = $this->get('/employees/'.$this->subject())->getContent();

        $this->assertStringNotContainsString((string) $banking->pan, $html);
        $this->assertStringNotContainsString((string) $banking->id_proof_number, $html);
    }

    /* ══════════════════════════════════════════════════════════════════════
       IT DOES NOT LINGER
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_value_is_gone_on_the_next_page_load(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $banking = $this->banking();

        $this->post('/employees/'.$this->subject().'/reveal', [
            'field' => 'account_number',
            'reason' => 'Confirming a bounced transfer.',
        ]);

        $this->get('/employees/'.$this->subject());
        $second = $this->get('/employees/'.$this->subject())->getContent();

        $this->assertStringNotContainsString($banking->account_number, $second);
        $this->assertStringContainsString('•••• •••• '.substr($banking->account_number, -4), $second);
    }

    public function test_a_reveal_cannot_be_a_get(): void
    {
        /*
         * The property that makes the log trustworthy. A GET is prefetched by
         * browsers, kept in history and repeated by a back button, and each of
         * those would record a look that nobody took.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/employees/'.$this->subject().'/reveal')->assertStatus(405);
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT IT WRITES DOWN
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_reveal_is_audited_with_who_whose_and_why(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/'.$this->subject().'/reveal', [
            'field' => 'account_number',
            'reason' => 'A transfer bounced.',
        ]);

        $entry = DB::table('audit_log')
            ->where('action', AuditLog::IDENTIFIER_REVEALED)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('employee', $entry->entity_type);
        $this->assertSame($this->subject(), $entry->entity_id);
        $this->assertStringContainsString('A transfer bounced.', (string) $entry->after_json);
        $this->assertStringContainsString('Account number', (string) $entry->after_json);
    }

    public function test_the_audit_entry_never_carries_the_number_itself(): void
    {
        /*
         * Otherwise the log becomes the leak: it is readable on the Admin
         * Panel's audit screen, it is the one table nothing may edit, and it
         * would then hold in plaintext the value the column beside it encrypts.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $banking = $this->banking();

        $this->post('/employees/'.$this->subject().'/reveal', [
            'field' => 'account_number',
            'reason' => 'A transfer bounced.',
        ]);

        $entry = DB::table('audit_log')->where('action', AuditLog::IDENTIFIER_REVEALED)->first();

        foreach ([$entry->after_json, $entry->before_json] as $payload) {
            $this->assertStringNotContainsString($banking->account_number, (string) $payload);
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT IT REFUSES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_reason_is_required(): void
    {
        // The field that makes the entry answerable months later. Without it
        // the log records that somebody looked and nothing about whether they
        // should have.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/'.$this->subject().'/reveal', [
            'field' => 'account_number',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, DB::table('audit_log')->where('action', AuditLog::IDENTIFIER_REVEALED)->count());
    }

    public function test_only_the_three_identifiers_can_be_asked_for(): void
    {
        /*
         * The field name arrives from the request and is used to read a column.
         * Unchecked, "password" or "remember_token" would be just as valid a
         * thing to ask for.
         */
        $this->signInAsStaff(['employee', 'hr']);

        foreach (['password', 'remember_token', 'ifsc; drop table users'] as $field) {
            $this->post('/employees/'.$this->subject().'/reveal', [
                'field' => $field,
                'reason' => 'Curiosity.',
            ])->assertSessionHasErrors('field');
        }
    }

    public function test_a_record_with_nothing_on_file_reveals_nothing(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $without = \App\Models\Employee::query()
            ->whereNotIn('id', EmployeeBanking::pluck('employee_id'))
            ->with('user')
            ->firstOrFail();

        $this->post('/employees/'.$without->user->user_id.'/reveal', [
            'field' => 'account_number',
            'reason' => 'Checking.',
        ])->assertNotFound();

        // And nothing is written: there was no look to record.
        $this->assertSame(0, DB::table('audit_log')->where('action', AuditLog::IDENTIFIER_REVEALED)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    protected function banking(): EmployeeBanking
    {
        return EmployeeBanking::with('employee.user')->firstOrFail();
    }

    protected function subject(): string
    {
        return (string) $this->banking()->employee->user->user_id;
    }
}
