<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryStructure as Structure;
use App\Models\MasterDataItem;
use App\Support\Audit\AuditLog;
use App\Support\IdProof;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The standing agreement about somebody's pay.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * AN INPUT TO A PAYSLIP, NOT A PAYSLIP
 *
 * This application still does not calculate payroll (2026-08-27, unchanged).
 * HR works a month out in a spreadsheet and uploads the payslip that produced.
 * What landed on 2026-09-12 is the agreement underneath it — Basic, HRA, other
 * allowances, PF, PT, TDS — recorded so the person preparing that spreadsheet
 * has the figures to start from.
 *
 * So there is no total anywhere, and the Salary page does not show this at all.
 * A breakdown printed beside a payslip invites a comparison, and the two
 * disagree in any month carrying a deduction, an arrear or a day of loss of
 * pay — at which point the screen is arguing with the document somebody was
 * actually paid against.
 *
 * THREE ENGAGEMENTS, THREE SHAPES
 *
 * Full-time gets the six components. An intern gets one monthly stipend, with
 * no PF and no HRA line, because neither is true of a stipend. A freelancer
 * gets a rate and a basis, recorded for the file — their payments happen
 * outside the CRM entirely.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmployeeSalaryStructureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /* ══════════════════════════════════════════════════════════════════════
       RECORDING IT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_full_time_agreement_is_stored_in_minor_units(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload())->assertRedirect();

        $structure = Structure::firstOrFail();

        $this->assertSame(Structure::BREAKDOWN, $structure->kind);

        // Paise, never rupees, and never a float. ₹60,000 is 6,000,000 paise.
        $this->assertSame(6_000_000, $structure->basic_minor);
        $this->assertSame(2_400_000, $structure->hra_minor);
        $this->assertSame(600_000, $structure->allowances_minor);
        $this->assertSame(180_000, $structure->pf_minor);
        $this->assertSame(20_000, $structure->pt_minor);
        $this->assertSame(500_000, $structure->tds_minor);
    }

    public function test_paise_survive_the_round_trip(): void
    {
        /*
         * The reason none of this is a float. A figure typed with paise has to
         * come back as the same figure — 60000.55 is 6,000,055 paise and not
         * 6,000,054 after a binary rounding nobody sees until it is on a
         * payslip.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['basic' => '60000.55']))->assertRedirect();

        $this->assertSame(6_000_055, Structure::firstOrFail()->basic_minor);
    }

    public function test_a_full_time_hire_must_state_every_component(): void
    {
        // The owner's list: full-time, everything. A zero is a fine answer —
        // an empty box is not an answer at all.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'basic' => '',
            'pf' => '',
        ]))->assertSessionHasErrors(['basic', 'pf']);

        $this->assertSame(0, Employee::count());
    }

    public function test_zero_is_a_stated_answer_and_is_kept(): void
    {
        // "No professional tax is deducted" is a statement. It has to be
        // distinguishable from "nobody has said", which is null.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['pt' => '0']))->assertRedirect();

        $this->assertSame(0, Structure::firstOrFail()->pt_minor);
    }

    public function test_an_intern_gets_a_stipend_and_no_breakdown(): void
    {
        /*
         * There is no PF on a stipend and an HRA line on one would be a
         * fiction. The intern form asks for a single monthly amount, and the
         * columns the other shape uses stay null.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'employment_type' => Employee::INTERN,
            'pan' => '',
            'current_address' => '',
            'permanent_address' => '',
            'stipend' => '15000',
        ]))->assertRedirect();

        $structure = Structure::firstOrFail();

        $this->assertSame(Structure::STIPEND, $structure->kind);
        $this->assertSame(1_500_000, $structure->stipend_minor);
        $this->assertNull($structure->basic_minor);
        $this->assertNull($structure->pf_minor);
    }

    public function test_a_full_time_component_posted_for_an_intern_is_ignored(): void
    {
        /*
         * The shape decides what may be written, not the request. Posting
         * `basic` alongside an intern's stipend must not put an HRA-less
         * "basic" on a stipend row — the columns a kind does not use are not
         * merely unasked-for, they are meaningless for it.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'employment_type' => Employee::INTERN,
            'pan' => '',
            'current_address' => '',
            'permanent_address' => '',
            'stipend' => '15000',
            'basic' => '99999',
        ]))->assertRedirect();

        $this->assertNull(Structure::firstOrFail()->basic_minor);
    }

    public function test_a_freelancer_gets_a_rate_and_a_basis(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'employment_type' => Employee::FREELANCE,
            'current_address' => '',
            'permanent_address' => '',
            'rate' => '2500',
            'rate_basis' => 'hour',
        ]))->assertRedirect();

        $structure = Structure::firstOrFail();

        $this->assertSame(Structure::RATE, $structure->kind);
        $this->assertSame(250_000, $structure->rate_minor);
        $this->assertSame('hour', $structure->rate_basis);
    }

    public function test_a_rate_without_a_basis_is_refused(): void
    {
        // A rate with no basis is a number nobody can act on: ₹2,500 an hour
        // and ₹2,500 a project are not close to the same agreement.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'employment_type' => Employee::FREELANCE,
            'current_address' => '',
            'permanent_address' => '',
            'rate' => '2500',
            'rate_basis' => '',
        ]))->assertSessionHasErrors('rate_basis');
    }

    /* ══════════════════════════════════════════════════════════════════════
       REVISING IT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_blank_keeps_and_typed_replaces(): void
    {
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->post('/employees/'.$staffId, $this->editPayload([
            'basic' => '70000',
        ]))->assertRedirect();

        $structure = Structure::firstOrFail();

        $this->assertSame(7_000_000, $structure->basic_minor);
        // Untouched: one box was filled in and the others were left alone.
        $this->assertSame(2_400_000, $structure->hra_minor);
    }

    public function test_a_revision_is_audited_by_component_and_never_by_figure(): void
    {
        /*
         * What an audit trail has to answer is who changed somebody's salary
         * and when. Carrying the figures would make this table a permanent,
         * unredactable history of everybody's pay — reachable by anybody who
         * can open the audit screen, which is a wider set than `salary.view`.
         */
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->post('/employees/'.$staffId, $this->editPayload(['basic' => '70000']));

        $entries = DB::table('audit_log')
            ->where('action', AuditLog::SALARY_STRUCTURE_CHANGED)
            ->get();

        $this->assertCount(2, $entries);

        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('70000', (string) $entry->after_json);
            $this->assertStringNotContainsString('7000000', (string) $entry->after_json);
        }

        $this->assertStringContainsString('Basic', (string) $entries->last()->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO MAY READ IT, AND WHO MAY WRITE IT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_sees_the_card_on_the_record(): void
    {
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->get('/employees/'.$staffId)
            ->assertOk()
            ->assertSee('What they are paid')
            ->assertSee('₹60,000.00', false);
    }

    public function test_the_directory_permission_does_not_carry_the_pay_card(): void
    {
        // Seeing what a colleague earns is itself the harm — which is why
        // `salary.view` exists and why a Team Lead does not hold it.
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->signInAsStaff(['employee', 'team_lead']);

        $html = $this->get('/employees/'.$staffId)->assertOk()->getContent();

        $this->assertStringNotContainsString('What they are paid', $html);
        $this->assertStringNotContainsString('60,000', $html);
    }

    public function test_editing_a_record_without_salary_manage_cannot_move_a_salary(): void
    {
        /*
         * The gate that matters most here. Somebody granted `employees.edit`
         * and not `salary.manage` may correct a department — and posting pay
         * fields at the same form must change nothing. The rules are not built
         * for them, so the keys never reach the data the write reads.
         */
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->signInAsStaff(['employee']);
        $this->grant('employees.view', 'employees.edit');

        $this->post('/employees/'.$staffId, $this->editPayload([
            'basic' => '900000',
        ]))->assertRedirect();

        // Exactly as HR left it.
        $this->assertSame(6_000_000, Structure::firstOrFail()->basic_minor);
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
            'announce_milestones' => '1',

            // Required of every engagement since 2026-09-17.
            'phone' => '+91 98100 00000',

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
            'pan' => 'ABCDE1234F',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '50100234567890',
        ];
    }

    /**
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
            'announce_milestones' => '1',

            'current_address' => '',
            'permanent_address' => '',

            'basic' => '',
            'hra' => '',
            'allowances' => '',
            'pf' => '',
            'pt' => '',
            'tds' => '',

            'id_proof_type' => IdProof::AADHAAR,
            'id_proof_number' => '',
            'pan' => '',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '',
        ];
    }

    /**
     * Give the signed-in account permissions its roles do not carry.
     */
    protected function grant(string ...$permissions): void
    {
        $role = \App\Models\Role::firstOrCreate(
            ['role_key' => 'test_grant'],
            ['role_name' => 'Test grant', 'is_active' => true],
        );

        $role->permissions()->syncWithoutDetaching(
            \App\Models\Permission::whereIn('permission_key', $permissions)->pluck('id')
        );

        auth()->user()->roles()->syncWithoutDetaching([$role->id]);
        app(\App\Support\Rbac\Rbac::class)->forget(auth()->user());
    }
}
