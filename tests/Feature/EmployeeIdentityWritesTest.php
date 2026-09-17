<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBanking;
use App\Models\MasterDataItem;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\IdProof;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Entering somebody's identity documents and bank details.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * BLANK MEANS KEEP, AND THAT IS THE WHOLE DESIGN (decided 2026-09-14)
 *
 * The edit form cannot pre-fill these. A page that loads the real numbers into
 * inputs has put every one of them into the HTML, which is exactly what the
 * masking layer exists to prevent — and "it is only shown to HR" is not a
 * control, because the markup reaches the browser either way.
 *
 * So the inputs are empty, with the masked value beside them as a hint. Empty
 * keeps what is stored; typing replaces it. The failure this avoids is the
 * obvious alternative: a form that posts back the mask it was shown and writes
 * `XXXX XXXX 1234` over somebody's real Aadhaar.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmployeeIdentityWritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /* ══════════════════════════════════════════════════════════════════════
       CAPTURING IT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_adding_somebody_records_their_identity_and_bank_details(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload())->assertRedirect();

        $banking = EmployeeBanking::firstOrFail();

        $this->assertSame(IdProof::AADHAAR, $banking->id_proof_type);
        $this->assertSame('123456781234', $banking->id_proof_number);
        $this->assertSame('ABCDE1234F', $banking->pan);
        $this->assertSame('HDFC Bank', $banking->bank_name);
        $this->assertSame('HDFC0001234', $banking->ifsc);
        $this->assertSame('50100234567890', $banking->account_number);
    }

    public function test_the_identifiers_are_encrypted_in_the_column(): void
    {
        /*
         * Read straight from the table, past the model's cast. A database dump,
         * a backup on somebody's laptop and a read-only replica are all places
         * these end up, and none of them has a masking layer.
         */
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $row = DB::table('employee_banking')->first();

        $this->assertNotSame('123456781234', $row->id_proof_number);
        $this->assertNotSame('ABCDE1234F', $row->pan);
        $this->assertNotSame('50100234567890', $row->account_number);

        // The branch code is not identifying — it is printed on every cheque —
        // so it is stored plainly on purpose.
        $this->assertSame('HDFC0001234', $row->ifsc);
    }

    public function test_the_photocopy_date_records_what_the_office_actually_holds(): void
    {
        // The CRM holds the number; the photocopy is submitted on paper. Null
        // means nobody has it yet, which is the state HR needs to chase.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['id_proof_copy_received_on' => null]));

        $this->assertNull(EmployeeBanking::firstOrFail()->id_proof_copy_received_on);
    }

    /* ══════════════════════════════════════════════════════════════════════
       EDITING IT — BLANK KEEPS, TYPED REPLACES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_edit_form_shows_the_mask_and_never_the_number(): void
    {
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = User::where('email', 'new.person@example.test')->firstOrFail()->user_id;

        $html = $this->get('/employees/'.$staffId.'/edit')->assertOk()->getContent();

        $this->assertStringContainsString('XXXX XXXX 1234', $html);
        $this->assertStringNotContainsString('123456781234', $html);
        $this->assertStringNotContainsString('50100234567890', $html);
        $this->assertStringNotContainsString('ABCDE1234F', $html);
    }

    public function test_leaving_the_fields_blank_keeps_what_is_on_file(): void
    {
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $employee = Employee::firstOrFail();
        $staffId = $employee->user->user_id;

        // Somebody corrects a department and posts the form. Every identity
        // input is empty, because the form never had the values to begin with.
        $this->post('/employees/'.$staffId, $this->editPayload([
            'id_proof_number' => '',
            'pan' => '',
            'account_number' => '',
        ]))->assertRedirect();

        $banking = EmployeeBanking::firstOrFail();

        $this->assertSame('123456781234', $banking->id_proof_number);
        $this->assertSame('ABCDE1234F', $banking->pan);
        $this->assertSame('50100234567890', $banking->account_number);
    }

    public function test_typing_a_new_number_replaces_the_old_one(): void
    {
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->post('/employees/'.$staffId, $this->editPayload([
            'account_number' => '004501556789',
        ]))->assertRedirect();

        $this->assertSame('004501556789', EmployeeBanking::firstOrFail()->account_number);
    }

    public function test_changing_where_pay_lands_is_audited_without_the_number(): void
    {
        /*
         * An audit entry naming the new account number would put the value in
         * the one table this application refuses to let anybody edit. What is
         * worth recording is that it changed, and who changed it.
         */
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->post('/employees/'.$staffId, $this->editPayload([
            'account_number' => '004501556789',
        ]));

        $entry = DB::table('audit_log')
            ->where('action', AuditLog::SALARY_BANKING_CHANGED)
            ->first();

        $this->assertNotNull($entry);
        $this->assertStringNotContainsString('004501556789', (string) $entry->after_json);
        $this->assertStringContainsString('account', strtolower((string) $entry->after_json));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SHAPES THESE DOCUMENTS ACTUALLY HAVE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_malformed_pan_is_refused(): void
    {
        // Five letters, four digits, a check letter. A typo here reaches a
        // payslip and a tax filing.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['pan' => 'ABCD1234F']))
            ->assertSessionHasErrors('pan');

        $this->assertSame(0, EmployeeBanking::count());
    }

    public function test_an_aadhaar_of_the_wrong_length_is_refused(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['id_proof_number' => '12345678']))
            ->assertSessionHasErrors('id_proof_number');
    }

    public function test_a_passport_number_is_checked_against_the_passport_shape(): void
    {
        /*
         * The rule follows the DOCUMENT, not the column. Twelve digits is a
         * valid Aadhaar and not a valid passport, and a single "identity
         * number" rule loose enough for both would check nothing at all.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'id_proof_type' => IdProof::PASSPORT,
            'id_proof_number' => '123456781234',
        ]))->assertSessionHasErrors('id_proof_number');

        $this->post('/employees', $this->payload([
            'id_proof_type' => IdProof::PASSPORT,
            'id_proof_number' => 'M1234567',
        ]))->assertRedirect();
    }

    public function test_a_malformed_ifsc_is_refused(): void
    {
        // Four letters, a zero, six more characters. The zero is the part
        // people get wrong, and a wrong IFSC is a transfer that bounces.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['ifsc' => 'HDFC1001234']))
            ->assertSessionHasErrors('ifsc');
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT EACH ENGAGEMENT MUST PRODUCE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_full_time_hire_must_produce_everything(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'pan' => '',
            'account_number' => '',
            'id_proof_number' => '',
        ]))->assertSessionHasErrors(['pan', 'account_number', 'id_proof_number']);
    }

    public function test_an_intern_is_not_required_to_have_a_pan(): void
    {
        // Students frequently do not have one, and refusing the record would
        // mean the intern cannot be added at all.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'employment_type' => Employee::INTERN,
            'pan' => '',
        ]))->assertRedirect();

        $this->assertSame(1, EmployeeBanking::count());
    }

    public function test_a_freelancer_must_have_a_pan(): void
    {
        // They invoice us, so it is needed for TDS.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'employment_type' => Employee::FREELANCE,
            'pan' => '',
        ]))->assertSessionHasErrors('pan');
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New Person',
            'email' => 'new.person@example.test',
            'employment_type' => Employee::FULL_TIME,
            'department_id' => MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->value('id'),
            'designation_id' => MasterDataItem::inList(MasterDataItem::DESIGNATIONS)->value('id'),
            'joined_on' => Carbon::now()->subMonth()->toDateString(),
            'date_of_birth' => '1995-04-11',
            'announce_milestones' => '1',

            // Required of every engagement since 2026-09-17.
            'phone' => '+91 98100 00000',

            // Required for a full-time hire, which is what this payload is.
            'current_address' => "1 Somewhere Road\nKolkata",
            'permanent_address' => "2 Elsewhere Lane\nHowrah",
            'basic' => '60000',
            'hra' => '24000',
            'allowances' => '6000',
            'pf' => '1800',
            'pt' => '200',
            'tds' => '5000',

            'id_proof_type' => IdProof::AADHAAR,
            'id_proof_number' => '123456781234',
            'id_proof_copy_received_on' => Carbon::now()->subWeek()->toDateString(),
            'pan' => 'ABCDE1234F',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '50100234567890',
        ];
    }

    /**
     * The edit form's payload: identity inputs empty, as the form renders them.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function editPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New Person',
            'email' => 'new.person@example.test',
            'department_id' => MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->value('id'),
            'designation_id' => MasterDataItem::inList(MasterDataItem::DESIGNATIONS)->value('id'),
            'joined_on' => Carbon::now()->subMonth()->toDateString(),
            'date_of_birth' => '1995-04-11',
            'announce_milestones' => '1',

            // Prefilled by the real form for anybody who may read them; empty
            // here, which keeps what is stored.
            'current_address' => '',
            'permanent_address' => '',

            'id_proof_type' => IdProof::AADHAAR,
            'id_proof_number' => '',
            'id_proof_copy_received_on' => Carbon::now()->subWeek()->toDateString(),
            'pan' => '',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '',
        ];
    }
}
