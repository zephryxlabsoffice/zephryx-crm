<?php

namespace Tests\Feature;

use App\Models\EmployeeBanking;
use App\Models\Permission;
use App\Support\IdProof;
use Tests\TestCase;

/**
 * Somebody else's identity documents, on the record page.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A REVERSAL, RECORDED RATHER THAN QUIETLY APPLIED (2026-09-12)
 *
 * Until now nobody saw anybody else's identifiers at all — not HR, not the CEO
 * — and payroll was built around that: it pays people from a bank transfer file
 * nobody reads rather than from a screen anybody can open.
 *
 * The owner reversed it: HR and the CEO may see them, one field at a time, with
 * an audit entry naming who looked at whose record. This file covers the half
 * that arrives first — the card exists, it is MASKED, and it is behind its own
 * sensitive permission. The reveal itself lands next, and until it does the
 * full values are on no page at all.
 *
 * WHY A SEPARATE PERMISSION AND NOT `employees.view`
 *
 * Every staff role above Employee holds `employees.view` — that is the
 * directory, the thing people use to find a colleague's extension. Identity
 * documents are not the directory. A Team Lead looking up who is in Design must
 * not thereby be looking at their passport number.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmployeeIdentityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The demo people come with banking details on file, so the card has
        // something real to mask. An empty card would pass every assertion
        // below while proving none of them.
        $this->seedDemoWorkforce();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PERMISSION
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_permission_exists_and_is_marked_sensitive(): void
    {
        /*
         * A route guarded by a key with no row fails CLOSED — Rbac::can cannot
         * grant a permission that does not exist — so a missing row here would
         * lock HR out silently rather than raise anything.
         */
        $permission = Permission::where('permission_key', 'employees.identifiers')->first();

        $this->assertNotNull($permission);
        $this->assertTrue((bool) $permission->is_sensitive);
        $this->assertSame('employees', $permission->module);
    }

    public function test_the_permission_can_be_granted_from_the_admin_panel(): void
    {
        // A permission HR holds that the Access Control screen cannot show or
        // take away is worse than one nobody holds at all.
        $this->assertContains(
            'employees.identifiers',
            \App\Support\Admin\AccessDirectory::assignableKeys(),
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO SEES THE CARD
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_sees_the_card_masked(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        /*
         * An Aadhaar holder specifically, not merely the first row. The demo
         * people carry all four document types since 2026-09-14, and pinning
         * this to whichever row came back first would make the assertion below
         * depend on fixture ordering rather than on the masking rule.
         */
        $banking = EmployeeBanking::where('id_proof_type', IdProof::AADHAAR)->firstOrFail();
        $subject = $banking->employee->user->user_id;

        $html = $this->get('/employees/'.$subject)->assertOk()->getContent();

        // The document is named, and its number is masked by that document's
        // own rule — see App\Support\IdProof.
        $this->assertStringContainsString('Aadhaar', $html);
        $this->assertStringContainsString('XXXX XXXX '.substr($banking->id_proof_number, -4), $html);
        $this->assertStringContainsString('•••• •••• '.substr($banking->account_number, -4), $html);
    }

    public function test_the_full_numbers_are_on_no_page_at_all(): void
    {
        /*
         * The rule this module exists to keep: masking happens in PHP, before
         * the value reaches the view. A full number hidden behind CSS or parked
         * in a data attribute has already been read by whoever is sitting at
         * the browser.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $banking = EmployeeBanking::firstOrFail();
        $html = $this->get('/employees/'.$banking->employee->user->user_id)->getContent();

        foreach ([$banking->id_proof_number, $banking->account_number, $banking->pan] as $secret) {
            $this->assertStringNotContainsString((string) $secret, $html);
        }
    }

    public function test_the_directory_permission_does_not_carry_the_identity_card(): void
    {
        // A Team Lead may look up a colleague. That is not the same act as
        // looking at their passport.
        $this->signInAsStaff(['employee', 'team_lead']);

        $banking = EmployeeBanking::firstOrFail();

        $html = $this->get('/employees/'.$banking->employee->user->user_id)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Identity and payment', $html);
        $this->assertStringNotContainsString('XXXX XXXX', $html);
        $this->assertStringNotContainsString('••••', $html);
    }

    public function test_a_record_with_nothing_on_file_says_so(): void
    {
        /*
         * The state that matters operationally: somebody nobody has set up
         * cannot be paid. It has to be visible as an absence rather than as a
         * card that simply is not rendered.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $withNothing = \App\Models\Employee::query()
            ->whereNotIn('id', EmployeeBanking::pluck('employee_id'))
            ->with('user')
            ->firstOrFail();

        $this->get('/employees/'.$withNothing->user->user_id)
            ->assertOk()
            ->assertSee('Nothing on file', false);
    }
}
