<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\MasterDataItem;
use App\Support\Audit\AuditLog;
use App\Support\IdProof;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The two addresses on somebody's record.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY TWO, AND WHY THEY ARE NOT THE SAME QUESTION ASKED TWICE
 *
 * Current is where a courier goes and changes when somebody moves. Permanent is
 * the address printed on the ID proof HR checked them against, and it is what
 * the photocopy in the file has to match. In one column the second overwrites
 * the first and nobody can tell afterwards which of the two the stored line is.
 *
 * WHAT IS BEHIND THE PERMISSION AND WHAT IS NOT
 *
 * Reading them needs `employees.identifiers` — the permission the directory
 * does not carry — for the same reason the identity card does: a Team Lead
 * looking up a colleague's department is not thereby looking up their flat.
 *
 * But unlike the card, they are shown IN FULL. An address is not a credential:
 * half of one protects nothing and cannot be checked against the paper copy,
 * which is the only thing anybody reads it for.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmployeeAddressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /* ══════════════════════════════════════════════════════════════════════
       CAPTURING THEM
       ══════════════════════════════════════════════════════════════════════ */

    public function test_adding_somebody_records_both_addresses(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload())->assertRedirect();

        $profile = EmployeeProfile::firstOrFail();

        $this->assertSame("1 Somewhere Road\nKolkata", $profile->current_address);
        $this->assertSame("2 Elsewhere Lane\nHowrah", $profile->permanent_address);
    }

    public function test_a_full_time_hire_must_produce_both(): void
    {
        // The owner's list (2026-09-12): full-time, everything.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'current_address' => '',
            'permanent_address' => '',
        ]))->assertSessionHasErrors(['current_address', 'permanent_address']);

        $this->assertSame(0, Employee::count());
    }

    public function test_an_intern_may_be_added_without_one(): void
    {
        /*
         * An address is in nobody's required list but the full-time one. A
         * student joining for three months is asked for an ID proof, a phone
         * number and a bank account, and refusing the record over a missing
         * address would mean they cannot be added at all.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'employment_type' => Employee::INTERN,
            'pan' => '',
            'current_address' => '',
            'permanent_address' => '',
        ]))->assertRedirect();

        $this->assertSame(1, Employee::count());

        /*
         * There IS a profile row — the phone number is required of everybody
         * and lives on it — and both address columns are null. Which is the
         * distinction that matters: "not stated" is a chaseable gap, and it is
         * not the same as a row of empty strings saying somebody was asked and
         * answered nothing.
         */
        $profile = EmployeeProfile::firstOrFail();

        $this->assertSame('+91 98100 00000', $profile->phone);
        $this->assertNull($profile->current_address);
        $this->assertNull($profile->permanent_address);
    }

    /* ══════════════════════════════════════════════════════════════════════
       EDITING THEM — BLANK KEEPS, TYPED REPLACES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_leaving_them_blank_keeps_what_is_on_file(): void
    {
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->post('/employees/'.$staffId, $this->editPayload())->assertRedirect();

        $profile = EmployeeProfile::firstOrFail();

        $this->assertSame("1 Somewhere Road\nKolkata", $profile->current_address);
        $this->assertSame("2 Elsewhere Lane\nHowrah", $profile->permanent_address);
    }

    public function test_typing_a_new_address_replaces_the_old_one(): void
    {
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->post('/employees/'.$staffId, $this->editPayload([
            'current_address' => "9 New Street\nKolkata",
        ]))->assertRedirect();

        $profile = EmployeeProfile::firstOrFail();

        $this->assertSame("9 New Street\nKolkata", $profile->current_address);
        // Untouched: one box was filled in, the other was not.
        $this->assertSame("2 Elsewhere Lane\nHowrah", $profile->permanent_address);
    }

    public function test_the_change_is_audited_by_field_and_never_by_value(): void
    {
        /*
         * An audit entry is kept forever and nothing may edit it. Recording
         * that an address changed is worth that; keeping a permanent copy of
         * every address somebody has ever lived at is not.
         */
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->post('/employees/'.$staffId, $this->editPayload([
            'current_address' => "9 New Street\nKolkata",
        ]));

        $entries = DB::table('audit_log')
            ->where('action', AuditLog::EMPLOYEE_ADDRESS_CHANGED)
            ->get();

        // Two: the one that recorded them, and the one that changed one.
        $this->assertCount(2, $entries);

        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('New Street', (string) $entry->after_json);
            $this->assertStringNotContainsString('Somewhere Road', (string) $entry->after_json);
        }

        $this->assertStringContainsString('Current address', (string) $entries->last()->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO READS THEM
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_sees_them_in_full(): void
    {
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->get('/employees/'.$staffId)
            ->assertOk()
            ->assertSee('where they live')
            ->assertSee('1 Somewhere Road', false)
            // The phone and the personal address sit on the same card, behind
            // the same permission.
            ->assertSee('+91 98100 00000');
    }

    public function test_the_directory_permission_does_not_carry_them(): void
    {
        // Same rule as the identity card: looking up a colleague is not the
        // same act as looking up where they sleep.
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->signInAsStaff(['employee', 'team_lead']);

        $html = $this->get('/employees/'.$staffId)->assertOk()->getContent();

        $this->assertStringNotContainsString('where they live', $html);
        $this->assertStringNotContainsString('Somewhere Road', $html);
        $this->assertStringNotContainsString('+91 98100 00000', $html);
    }

    public function test_the_edit_form_prefills_them_for_whoever_may_read_them(): void
    {
        /*
         * Prefilled, unlike the identity fields beside them. There is nothing
         * to withhold from somebody who may already read the card, and an
         * address is corrected a line at a time rather than retyped from
         * memory — a blank box would make every correction a re-entry.
         */
        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees', $this->payload());

        $staffId = Employee::firstOrFail()->user->user_id;

        $this->get('/employees/'.$staffId.'/edit')
            ->assertOk()
            ->assertSee('1 Somewhere Road', false);
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

            // Required of a full-time hire too, and about nothing in this file.
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
     * The edit form's payload: identity empty as the form renders it, and the
     * addresses empty too unless a case fills one in.
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
            'announce_milestones' => '1',

            'current_address' => '',
            'permanent_address' => '',

            'id_proof_type' => IdProof::AADHAAR,
            'id_proof_number' => '',
            'pan' => '',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '',
        ];
    }
}
